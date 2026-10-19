<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Integration;

use App\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Models\ItemRanking;
use Polis\Plugin\Voting\Models\VotingComparison;
use Polis\Plugin\Voting\Models\VotingSession;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugin\Voting\Support\VotableItem;
use Polis\Plugin\Voting\Tests\Application\ApplicationTestCase;

/**
 * Integration coverage for the engine against a real DB, exercising the
 * session -> compare -> vote -> ranking flow across ALL THREE systems.
 */
final class HeadToHeadVotingServiceTest extends ApplicationTestCase
{
    use RefreshDatabase;

    private HeadToHeadVotingService $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new HeadToHeadVotingService;
    }

    /**
     * @return array<int, VotableItem>
     */
    private function candidates(int $count, ?int $listId = null): array
    {
        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $c = Candidate::create(['title' => "Item {$i}", 'list_id' => $listId]);
            $items[] = new VotableItem(Candidate::class, $c->id, $listId);
        }

        return $items;
    }

    // ---- LIST_RANKING ------------------------------------------------

    public function test_list_ranking_persists_session_and_rankings(): void
    {
        $items = $this->candidates(4);

        $session = $this->engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);

        $this->assertDatabaseHas((new VotingSession)->getTable(), [
            'id' => $session->id,
            'system' => 'list_ranking',
            'voting_type' => 'my_list',
            'status' => 'active',
        ]);
        // A ranking row exists for each candidate (persistent system).
        $this->assertSame(4, ItemRanking::query()->where('owner_id', 1)->where('voting_type', 'my_list')->count());
    }

    public function test_vote_records_normalized_comparison_and_updates_both_rankings(): void
    {
        $items = $this->candidates(2);
        $session = $this->engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);

        $pair = $this->engine->nextComparison($session);
        $this->assertNotNull($pair);

        $this->engine->recordVote($session, $pair[0], $pair[1]);

        // Exactly one comparison row, normalised (item_a handle <= item_b handle).
        $comparison = VotingComparison::query()->firstOrFail();
        $this->assertLessThanOrEqual(
            $comparison->item_b_type.'#'.$comparison->item_b_id,
            $comparison->item_a_type.'#'.$comparison->item_a_id,
        );

        $winner = ItemRanking::query()->where('votable_id', $pair[0]->id)->firstOrFail();
        $loser = ItemRanking::query()->where('votable_id', $pair[1]->id)->firstOrFail();

        $this->assertSame(1, $winner->wins);
        $this->assertSame(1, $winner->total_comparisons);
        $this->assertEqualsWithDelta(1.0, (float) $winner->aggregate_score, 1e-9);
        $this->assertSame(0, $loser->wins);
        $this->assertSame(1, $loser->total_comparisons);
        $this->assertEqualsWithDelta(0.0, (float) $loser->aggregate_score, 1e-9);
    }

    public function test_winner_order_does_not_change_stored_pair(): void
    {
        $items = $this->candidates(2);
        $session = $this->engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);

        // Vote with items in one order, then (after force-clear) the other — the
        // stored pair is identical either way.
        $this->engine->recordVote($session, $items[0], $items[1]);
        $first = VotingComparison::query()->firstOrFail()->only(['item_a_id', 'item_b_id']);

        VotingComparison::query()->forceDelete();
        $this->engine->recordVote($session, $items[1], $items[0]);
        $second = VotingComparison::query()->firstOrFail()->only(['item_a_id', 'item_b_id']);

        $this->assertSame($first, $second);
    }

    public function test_revoting_same_pair_replaces_not_duplicates(): void
    {
        $items = $this->candidates(2);
        $session = $this->engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);

        $this->engine->recordVote($session, $items[0], $items[1]);
        $this->engine->recordVote($session, $items[1], $items[0]);

        $this->assertSame(1, VotingComparison::query()->count());
    }

    public function test_aggregate_score_is_win_rate_over_multiple_votes(): void
    {
        $items = $this->candidates(3);
        $session = $this->engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);

        // item0 beats item1 and item2 -> 2/2 = 1.0.
        $this->engine->recordVote($session, $items[0], $items[1]);
        $this->engine->recordVote($session, $items[0], $items[2]);

        $r = ItemRanking::query()->where('votable_id', $items[0]->id)->firstOrFail();
        $this->assertSame(2, $r->total_comparisons);
        $this->assertEqualsWithDelta(1.0, (float) $r->aggregate_score, 1e-9);
    }

    public function test_rankings_sorted_best_first(): void
    {
        $items = $this->candidates(3);
        $session = $this->engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);

        $this->engine->recordVote($session, $items[0], $items[1]); // 0 up
        $this->engine->recordVote($session, $items[0], $items[2]); // 0 up
        $this->engine->recordVote($session, $items[1], $items[2]); // 1 up over 2

        $rankings = $this->engine->rankings('user', 1, 'my_list');
        $ids = array_map(static fn (ItemRanking $r): int => $r->votable_id, $rankings);

        $this->assertSame([$items[0]->id, $items[1]->id, $items[2]->id], $ids);
    }

    public function test_next_comparison_never_repeats_a_pair_and_exhausts(): void
    {
        $items = $this->candidates(3); // 3 possible pairs
        $session = $this->engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);

        $seen = [];
        for ($i = 0; $i < 3; $i++) {
            $pair = $this->engine->nextComparison($session);
            $this->assertNotNull($pair, "expected a pair on iteration {$i}");
            $handles = [$pair[0]->handle(), $pair[1]->handle()];
            sort($handles);
            $key = implode('|', $handles);
            $this->assertArrayNotHasKey($key, $seen);
            $seen[$key] = true;
            $this->engine->recordVote($session, $pair[0], $pair[1]);
        }

        // All 3 pairs compared -> no more.
        $this->assertNull($this->engine->nextComparison($session));
    }

    public function test_complete_locks_focus_placement(): void
    {
        $items = $this->candidates(3);
        $focus = $items[0];
        $session = $this->engine->startSession(
            'user', 1, VotingSystem::LIST_RANKING, 'my_list', $items, ['focus' => $focus]
        );
        $this->engine->recordVote($session, $items[0], $items[1]);

        $this->engine->completeSession($session);

        $ranking = ItemRanking::query()->where('votable_id', $focus->id)->firstOrFail();
        $this->assertNotNull($ranking->placement_locked_at);
    }

    // ---- CROSS_LIST --------------------------------------------------

    public function test_cross_list_only_pairs_items_from_different_lists(): void
    {
        // Two lists of two items each.
        $listA = $this->candidates(2, listId: 10);
        $listB = $this->candidates(2, listId: 20);
        $items = array_merge($listA, $listB);

        $session = $this->engine->startSession('user', 1, VotingSystem::CROSS_LIST, 'top_picks', $items);

        // Every offered pair must straddle the two lists.
        for ($i = 0; $i < 4; $i++) {
            $pair = $this->engine->nextComparison($session);
            if ($pair === null) {
                break;
            }
            $this->assertNotSame(
                $pair[0]->listId,
                $pair[1]->listId,
                'cross-list must never pair two same-list items',
            );
            $this->engine->recordVote($session, $pair[0], $pair[1]);
        }
    }

    public function test_cross_list_persists_rankings(): void
    {
        $items = array_merge($this->candidates(1, 10), $this->candidates(1, 20));
        $session = $this->engine->startSession('user', 1, VotingSystem::CROSS_LIST, 'top_picks', $items);

        $pair = $this->engine->nextComparison($session);
        $this->assertNotNull($pair);
        $this->engine->recordVote($session, $pair[0], $pair[1]);

        $this->assertSame(2, ItemRanking::query()->where('voting_type', 'top_picks')->count());
    }

    // ---- DECISION_HELPER ---------------------------------------------

    public function test_decision_helper_does_not_persist_rankings(): void
    {
        $items = $this->candidates(3);
        $session = $this->engine->startSession('user', 1, VotingSystem::DECISION_HELPER, 'what_to_pick', $items);

        $pair = $this->engine->nextComparison($session);
        $this->assertNotNull($pair);
        $this->engine->recordVote($session, $pair[0], $pair[1]);

        // Ephemeral system writes no ranking rows.
        $this->assertSame(0, ItemRanking::query()->where('voting_type', 'what_to_pick')->count());
        // But the comparison itself is recorded (session state).
        $this->assertSame(1, VotingComparison::query()->count());
    }

    public function test_decision_helper_clears_prior_ephemeral_rankings_on_start(): void
    {
        // Seed a stale ephemeral ranking row.
        ItemRanking::query()->create([
            'owner_type' => 'user', 'owner_id' => 1,
            'votable_type' => Candidate::class, 'votable_id' => 999,
            'voting_type' => 'what_to_pick', 'wins' => 3, 'total_comparisons' => 3, 'aggregate_score' => 1.0,
        ]);

        $items = $this->candidates(2);
        $this->engine->startSession('user', 1, VotingSystem::DECISION_HELPER, 'what_to_pick', $items);

        $this->assertSame(0, ItemRanking::query()->where('voting_type', 'what_to_pick')->count());
    }

    public function test_find_the_best_tournament_completes_at_win_goal(): void
    {
        $items = $this->candidates(3);
        $session = $this->engine->startSession(
            'user', 1, VotingSystem::DECISION_HELPER, 'find_best', $items,
            ['is_find_the_best' => true, 'win_goal' => 2]
        );

        // The initial champion wins twice -> reaches the goal -> completes.
        for ($i = 0; $i < 5; $i++) {
            $pair = $this->engine->nextComparison($session);
            if ($pair === null) {
                break;
            }
            // Champion is always presented; make it win.
            $champion = new VotableItem((string) $session->champion_votable_type, (int) $session->champion_votable_id);
            $winner = $pair[0]->equals($champion) ? $pair[0] : $pair[1];
            $loser = $pair[0]->equals($champion) ? $pair[1] : $pair[0];
            $this->engine->recordVote($session, $winner, $loser);
            $session->refresh();
        }

        $this->assertSame(VotingSession::STATUS_COMPLETED, $session->status);
        $this->assertGreaterThanOrEqual(2, $session->champion_wins);
    }
}
