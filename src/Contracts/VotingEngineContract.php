<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Contracts;

use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Models\ItemRanking;
use Polis\Plugin\Voting\Models\VotingSession;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugin\Voting\Support\VotableItem;

/**
 * The entity-agnostic head-to-head voting engine.
 *
 * ONE pairwise mechanism, THREE user-selectable systems (list ranking,
 * cross-list prioritization, decision helper). A consumer app binds its own
 * implementation over this contract (the plugin registers a consumer-override-
 * aware binding), or uses the shipped {@see HeadToHeadVotingService}.
 *
 * `system` and `voting_type` are REQUIRED on {@see startSession()} and are never
 * defaulted.
 */
interface VotingEngineContract
{
    /**
     * Start a voting session over a set of candidate items.
     *
     * @param  string  $ownerType  Polymorphic owner type (e.g. App\Models\User).
     * @param  int  $ownerId  Owner id.
     * @param  VotingSystem  $system  REQUIRED engine system.
     * @param  string  $votingType  REQUIRED consumer category label.
     * @param  array<int, VotableItem>  $items  The candidate pool to rank.
     * @param  array<string, mixed>  $options  subset_only, is_comprehensive, is_revote,
     *                                         is_find_the_best, win_goal, list_id,
     *                                         focus (VotableItem).
     */
    public function startSession(
        string $ownerType,
        int $ownerId,
        VotingSystem $system,
        string $votingType,
        array $items,
        array $options = []
    ): VotingSession;

    /**
     * The next pair to present, or null when the session is complete.
     *
     * @return array{0: VotableItem, 1: VotableItem}|null
     */
    public function nextComparison(VotingSession $session): ?array;

    /**
     * Record the outcome of a comparison. The winner/loser must be the two items
     * of a valid pair. Persists the comparison, updates rankings (unless the
     * system is ephemeral), and advances session state.
     */
    public function recordVote(
        VotingSession $session,
        VotableItem $winner,
        VotableItem $loser,
        ?VotableItem $firstShown = null
    ): void;

    /**
     * Record a skipped comparison (no winner).
     */
    public function skip(VotingSession $session, VotableItem $itemA, VotableItem $itemB): void;

    /**
     * Mark a session complete; lock placements for persistent systems.
     */
    public function completeSession(VotingSession $session): void;

    /**
     * The current ranking rows for an owner + voting_type, best first.
     *
     * @return array<int, ItemRanking>
     */
    public function rankings(string $ownerType, int $ownerId, string $votingType): array;

    /**
     * A progress summary for an active session (votes cast, estimated completion).
     *
     * @return array<string, mixed>
     */
    public function sessionProgress(VotingSession $session): array;
}
