<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Services;

use Illuminate\Support\Carbon;
use Polis\Plugin\Voting\Contracts\VotingEngineContract;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Models\ItemRanking;
use Polis\Plugin\Voting\Models\VotingComparison;
use Polis\Plugin\Voting\Models\VotingSession;
use Polis\Plugin\Voting\Support\VotableItem;

/**
 * Entity-agnostic head-to-head (pairwise) voting engine.
 *
 * ONE mechanism, THREE systems (see {@see VotingSystem}). The engine persists
 * comparisons + per-owner rankings and selects the next pair with the smart
 * weighting in {@see ScoreProximityWeighting}. It knows nothing about the domain
 * of the items it ranks — every item is a polymorphic {@see VotableItem}.
 *
 * This is the generalized distillation of HighScoresCenter's voting engine: the
 * single-list placement flow (LIST_RANKING), the cross-list top-N flow
 * (CROSS_LIST, same-list pairs forbidden) and the ephemeral decision flow
 * (DECISION_HELPER, incl. the "find the best" king-of-the-hill tournament).
 */
class HeadToHeadVotingService implements VotingEngineContract
{
    public function __construct(
        private readonly GroupProgressCalculator $progress = new GroupProgressCalculator,
    ) {}

    public function startSession(
        string $ownerType,
        int $ownerId,
        VotingSystem $system,
        string $votingType,
        array $items,
        array $options = []
    ): VotingSession {
        $items = array_values($items);

        $focus = $options['focus'] ?? null;
        $isRevote = (bool) ($options['is_revote'] ?? false);

        // Ephemeral systems start clean each session: clear any prior ephemeral
        // rankings for this owner + voting_type.
        if (! $system->isPersistent()) {
            $this->clearEphemeralRankings($ownerType, $ownerId, $votingType);
        }

        $session = new VotingSession;
        $session->owner_type = $ownerType;
        $session->owner_id = $ownerId;
        $session->system = $system->value;
        $session->voting_type = $votingType;
        $session->status = VotingSession::STATUS_ACTIVE;
        $session->subset_only = (bool) ($options['subset_only'] ?? false);
        $session->is_comprehensive = (bool) ($options['is_comprehensive'] ?? false);
        $session->is_revote = $isRevote;
        $session->is_find_the_best = (bool) ($options['is_find_the_best'] ?? false);
        $session->list_id = $options['list_id'] ?? null;
        $session->comparisons_made = 0;
        $session->current_group = 1;

        if ($focus instanceof VotableItem) {
            $session->focus_votable_type = $focus->type;
            $session->focus_votable_id = $focus->id;
        }

        // The candidate pool is pinned in session state so next-comparison
        // selection is deterministic and independent of the host's data source.
        $pool = array_map(static fn (VotableItem $i): array => $i->toArray(), $items);
        $placements = ['pool' => $pool];

        if ($session->is_find_the_best) {
            $placements['find_the_best_wins'] = [];
            $session->win_goal = (int) ($options['win_goal'] ?? max(3, (int) ceil(count($items) / 2)));
            if ($items !== []) {
                $champion = $items[0];
                $session->champion_votable_type = $champion->type;
                $session->champion_votable_id = $champion->id;
                $session->champion_wins = 0;
            }
        }

        $session->group_placements = $placements;
        $session->save();

        // Persistent systems ensure a ranking row exists for every candidate so
        // tallies have somewhere to land.
        if ($system->isPersistent()) {
            foreach ($items as $item) {
                $this->ensureRanking($session, $item);
            }
        }

        return $session;
    }

    public function nextComparison(VotingSession $session): ?array
    {
        if (! $session->isActive()) {
            return null;
        }

        $pool = $this->pool($session);
        if (count($pool) < 2) {
            return null;
        }

        $system = $session->systemEnum();

        if ($session->is_find_the_best) {
            return $this->nextFindTheBestPair($session, $pool);
        }

        $compared = $this->comparedHandles($session);

        // Build the candidate list respecting the focus item (if any) and the
        // system's same-list rule.
        $focus = $this->focusItem($session, $pool);

        if ($focus !== null) {
            $opponent = $this->selectOpponent($session, $focus, $pool, $compared, $system);

            return $opponent !== null ? [$focus, $opponent] : null;
        }

        // No fixed focus: pick the first uncompared eligible pair by weighting.
        return $this->selectAnyPair($session, $pool, $compared, $system);
    }

    public function recordVote(
        VotingSession $session,
        VotableItem $winner,
        VotableItem $loser,
        ?VotableItem $firstShown = null
    ): void {
        [$a, $b] = $this->normalisePair($winner, $loser);

        // Replace any prior (soft-deleted or live) comparison of this pair in the
        // session so re-voting updates rather than duplicates.
        $this->comparisonQuery($session)
            ->where('item_a_type', $a->type)->where('item_a_id', $a->id)
            ->where('item_b_type', $b->type)->where('item_b_id', $b->id)
            ->forceDelete();

        $comparison = new VotingComparison;
        $comparison->voting_session_id = $session->id;
        $comparison->item_a_type = $a->type;
        $comparison->item_a_id = $a->id;
        $comparison->item_b_type = $b->type;
        $comparison->item_b_id = $b->id;
        $comparison->winner_type = $winner->type;
        $comparison->winner_id = $winner->id;
        if ($firstShown !== null) {
            $comparison->first_shown_type = $firstShown->type;
            $comparison->first_shown_id = $firstShown->id;
        }
        $comparison->group = $session->current_group;
        $comparison->save();

        $session->comparisons_made++;
        $session->last_winner_type = $winner->type;
        $session->last_winner_id = $winner->id;

        if ($session->is_find_the_best) {
            $this->applyFindTheBest($session, $winner, $loser);
        }

        if ($session->systemEnum()->isPersistent()) {
            $this->applyRankingUpdate($session, $winner, won: true);
            $this->applyRankingUpdate($session, $loser, won: false);
        }

        $session->save();
    }

    public function skip(VotingSession $session, VotableItem $itemA, VotableItem $itemB): void
    {
        [$a, $b] = $this->normalisePair($itemA, $itemB);

        $comparison = new VotingComparison;
        $comparison->voting_session_id = $session->id;
        $comparison->item_a_type = $a->type;
        $comparison->item_a_id = $a->id;
        $comparison->item_b_type = $b->type;
        $comparison->item_b_id = $b->id;
        $comparison->skipped = true;
        $comparison->group = $session->current_group;
        $comparison->save();
    }

    public function completeSession(VotingSession $session): void
    {
        $session->status = VotingSession::STATUS_COMPLETED;
        $session->save();

        // Persistent systems lock the focus item's placement on completion.
        if ($session->systemEnum()->isPersistent() && $session->focus_votable_id !== null) {
            ItemRanking::query()
                ->where('owner_type', $session->owner_type)
                ->where('owner_id', $session->owner_id)
                ->where('voting_type', $session->voting_type)
                ->where('votable_type', $session->focus_votable_type)
                ->where('votable_id', $session->focus_votable_id)
                ->update(['placement_locked_at' => Carbon::now()]);
        }
    }

    public function rankings(string $ownerType, int $ownerId, string $votingType): array
    {
        /** @var array<int, ItemRanking> $rows */
        $rows = ItemRanking::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('voting_type', $votingType)
            ->orderByDesc('aggregate_score')
            ->orderByDesc('wins')
            ->orderBy('id')
            ->get()
            ->all();

        return $rows;
    }

    public function sessionProgress(VotingSession $session): array
    {
        $pool = $this->pool($session);
        $count = count($pool);
        $votes = $session->comparisons_made;

        $percent = $this->progress->calculateProgress(
            votesCast: $votes,
            groupCount: $count,
            placementStreak: $session->stable_votes_count ?? 0,
            candidatesInWindow: $count,
            isPointWindow: false,
        );

        return [
            'votes_cast' => $votes,
            'estimated_completion' => round($percent / 100, 4),
            'progress_percent' => round($percent, 2),
            'pool_size' => $count,
            'system' => $session->system,
            'voting_type' => $session->voting_type,
            'is_find_the_best' => $session->is_find_the_best,
            'champion' => $session->is_find_the_best && $session->champion_votable_id !== null
                ? ['type' => $session->champion_votable_type, 'id' => $session->champion_votable_id, 'wins' => $session->champion_wins]
                : null,
        ];
    }

    // -----------------------------------------------------------------
    // Selection internals
    // -----------------------------------------------------------------

    /**
     * @param  array<int, VotableItem>  $pool
     * @param  array<string, true>  $compared
     * @return array{0: VotableItem, 1: VotableItem}|null
     */
    private function selectAnyPair(VotingSession $session, array $pool, array $compared, VotingSystem $system): ?array
    {
        $scores = $this->scoresByHandle($session);

        foreach ($pool as $i => $focus) {
            $opponent = $this->selectOpponent($session, $focus, $pool, $compared, $system, $scores);
            if ($opponent !== null) {
                return [$focus, $opponent];
            }
        }

        return null;
    }

    /**
     * Pick the best opponent for a focus item: eligible, uncompared, weighted by
     * score proximity + low-comparison boost + pair freshness.
     *
     * @param  array<int, VotableItem>  $pool
     * @param  array<string, true>  $compared
     * @param  array<string, float>|null  $scores
     */
    private function selectOpponent(
        VotingSession $session,
        VotableItem $focus,
        array $pool,
        array $compared,
        VotingSystem $system,
        ?array $scores = null
    ): ?VotableItem {
        $scores ??= $this->scoresByHandle($session);
        $counts = $this->comparisonCountsByHandle($session);

        $focusScore = $scores[$focus->handle()] ?? 0.0;
        $minComparisons = $counts === [] ? 0 : (int) min($counts);
        $maxComparisons = $counts === [] ? 0 : (int) max($counts);

        $weights = [];
        $candidates = [];

        foreach ($pool as $candidate) {
            if ($candidate->equals($focus)) {
                continue;
            }
            // CROSS_LIST forbids same-list pairs.
            if (! $system->allowsSameListComparisons()
                && $candidate->listId !== null
                && $focus->listId !== null
                && $candidate->listId === $focus->listId) {
                continue;
            }
            $pairKey = $this->pairKey($focus, $candidate);
            if (isset($compared[$pairKey])) {
                continue;
            }

            $candidateScore = $scores[$candidate->handle()] ?? 0.0;
            $candidateComparisons = $counts[$candidate->handle()] ?? 0;

            $weight = ScoreProximityWeighting::proximityWeight($focusScore, $candidateScore)
                * ScoreProximityWeighting::lowComparisonBoost($candidateComparisons, $minComparisons, $maxComparisons)
                * ScoreProximityWeighting::pairFreshnessWeight(
                    ($counts[$focus->handle()] ?? 0) + $candidateComparisons
                );

            $idx = count($candidates);
            $candidates[$idx] = $candidate;
            $weights[$idx] = $weight;
        }

        if ($candidates === []) {
            return null;
        }

        $total = array_sum($weights);
        // Deterministic midpoint draw keeps selection reproducible/testable while
        // still honouring the weights (highest-weighted bucket wins the midpoint).
        $pick = ScoreProximityWeighting::pickByCumulativeWeight($weights, $total / 2);

        return $pick === null ? null : $candidates[$pick];
    }

    /**
     * @param  array<int, VotableItem>  $pool
     * @return array{0: VotableItem, 1: VotableItem}|null
     */
    private function nextFindTheBestPair(VotingSession $session, array $pool): ?array
    {
        if ($session->champion_votable_id === null) {
            return null;
        }
        $champion = new VotableItem((string) $session->champion_votable_type, (int) $session->champion_votable_id);

        $compared = $this->comparedHandles($session);
        foreach ($pool as $candidate) {
            if ($candidate->equals($champion)) {
                continue;
            }
            if (! isset($compared[$this->pairKey($champion, $candidate)])) {
                return [$champion, $candidate];
            }
        }

        return null;
    }

    private function applyFindTheBest(VotingSession $session, VotableItem $winner, VotableItem $loser): void
    {
        $wins = $session->group_placements['find_the_best_wins'] ?? [];
        $wins[$winner->handle()] = ($wins[$winner->handle()] ?? 0) + 1;

        $placements = $session->group_placements ?? [];
        $placements['find_the_best_wins'] = $wins;
        $session->group_placements = $placements;

        $isChampion = $session->champion_votable_type === $winner->type
            && (int) $session->champion_votable_id === $winner->id;

        if ($isChampion) {
            $session->champion_wins++;
        } else {
            $session->champion_votable_type = $winner->type;
            $session->champion_votable_id = $winner->id;
            $session->champion_wins = $wins[$winner->handle()];
        }

        if ($session->win_goal !== null && $session->champion_wins >= $session->win_goal) {
            $session->status = VotingSession::STATUS_COMPLETED;
        }
    }

    // -----------------------------------------------------------------
    // Ranking persistence
    // -----------------------------------------------------------------

    private function ensureRanking(VotingSession $session, VotableItem $item): ItemRanking
    {
        return ItemRanking::query()->firstOrCreate(
            [
                'owner_type' => $session->owner_type,
                'owner_id' => $session->owner_id,
                'votable_type' => $item->type,
                'votable_id' => $item->id,
                'voting_type' => $session->voting_type,
            ],
            [
                'list_id' => $item->listId,
                'wins' => 0,
                'total_comparisons' => 0,
                'aggregate_score' => 0,
            ],
        );
    }

    private function applyRankingUpdate(VotingSession $session, VotableItem $item, bool $won): void
    {
        $ranking = $this->ensureRanking($session, $item);

        if ($ranking->first_compared_at === null) {
            $ranking->first_compared_at = Carbon::now();
        }
        $ranking->last_compared_at = Carbon::now();

        if ($won) {
            $ranking->wins++;
        }
        $ranking->total_comparisons++;
        $ranking->aggregate_score = AggregateScoreCalculator::score($ranking->wins, $ranking->total_comparisons);
        $ranking->save();
    }

    private function clearEphemeralRankings(string $ownerType, int $ownerId, string $votingType): void
    {
        ItemRanking::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('voting_type', $votingType)
            ->forceDelete();
    }

    // -----------------------------------------------------------------
    // State helpers
    // -----------------------------------------------------------------

    /**
     * @return array<int, VotableItem>
     */
    private function pool(VotingSession $session): array
    {
        $pool = $session->group_placements['pool'] ?? [];

        return array_map(static fn (array $i): VotableItem => VotableItem::fromArray($i), $pool);
    }

    private function focusItem(VotingSession $session, array $pool): ?VotableItem
    {
        if ($session->focus_votable_id === null) {
            return null;
        }
        $focus = new VotableItem((string) $session->focus_votable_type, (int) $session->focus_votable_id);
        foreach ($pool as $item) {
            if ($item->equals($focus)) {
                return $item;
            }
        }

        return $focus;
    }

    /**
     * @return array<string, true>
     */
    private function comparedHandles(VotingSession $session): array
    {
        $rows = $this->comparisonQuery($session)->get(['item_a_type', 'item_a_id', 'item_b_type', 'item_b_id']);
        $compared = [];
        foreach ($rows as $row) {
            $a = new VotableItem($row->item_a_type, (int) $row->item_a_id);
            $b = new VotableItem($row->item_b_type, (int) $row->item_b_id);
            $compared[$this->pairKey($a, $b)] = true;
        }

        return $compared;
    }

    /**
     * @return array<string, float>
     */
    private function scoresByHandle(VotingSession $session): array
    {
        $rows = ItemRanking::query()
            ->where('owner_type', $session->owner_type)
            ->where('owner_id', $session->owner_id)
            ->where('voting_type', $session->voting_type)
            ->get(['votable_type', 'votable_id', 'aggregate_score']);

        $scores = [];
        foreach ($rows as $row) {
            $scores[$row->votable_type.'#'.$row->votable_id] = (float) $row->aggregate_score;
        }

        return $scores;
    }

    /**
     * @return array<string, int>
     */
    private function comparisonCountsByHandle(VotingSession $session): array
    {
        $rows = ItemRanking::query()
            ->where('owner_type', $session->owner_type)
            ->where('owner_id', $session->owner_id)
            ->where('voting_type', $session->voting_type)
            ->get(['votable_type', 'votable_id', 'total_comparisons']);

        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->votable_type.'#'.$row->votable_id] = (int) $row->total_comparisons;
        }

        return $counts;
    }

    private function comparisonQuery(VotingSession $session)
    {
        return VotingComparison::query()->where('voting_session_id', $session->id);
    }

    /**
     * Order-independent pair key across polymorphic items (uses the string
     * handle so types never collide).
     */
    private function pairKey(VotableItem $a, VotableItem $b): string
    {
        $handles = [$a->handle(), $b->handle()];
        sort($handles);

        return implode('|', $handles);
    }

    /**
     * Normalise a pair so item_a is the lexicographically-lower handle.
     *
     * @return array{0: VotableItem, 1: VotableItem}
     */
    private function normalisePair(VotableItem $x, VotableItem $y): array
    {
        return $x->handle() <= $y->handle() ? [$x, $y] : [$y, $x];
    }
}
