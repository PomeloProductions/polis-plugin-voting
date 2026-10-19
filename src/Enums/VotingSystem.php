<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Enums;

/**
 * The three systems the single head-to-head mechanism can run as.
 *
 * A session selects exactly one system (REQUIRED, never defaulted). The system
 * governs comparison eligibility and ranking persistence; the app-layer
 * `voting_type` string is an orthogonal, consumer-defined category label (e.g.
 * a tab name) that scopes rankings — the engine treats it as an opaque key.
 */
enum VotingSystem: string
{
    /**
     * Persistent pairwise ranking of items WITHIN one list. Same-list
     * comparisons are allowed. Produces the `aggregate_score` the CROSS_LIST
     * system consumes.
     */
    case LIST_RANKING = 'list_ranking';

    /**
     * Rank the top items ACROSS lists. ONLY cross-list comparisons are allowed
     * (two items from the same list are never paired). Depends on the
     * per-list aggregate_score produced by LIST_RANKING.
     */
    case CROSS_LIST = 'cross_list';

    /**
     * Ephemeral "which should I pick" ranking. Session data is cleared when the
     * session ends (nothing persists to item_rankings). Same-list comparisons
     * are allowed.
     */
    case DECISION_HELPER = 'decision_helper';

    /**
     * Whether rankings produced by this system persist beyond the session.
     */
    public function isPersistent(): bool
    {
        return $this !== self::DECISION_HELPER;
    }

    /**
     * Whether two items from the SAME list may be paired in a comparison.
     * CROSS_LIST forbids same-list pairs; the others allow them.
     */
    public function allowsSameListComparisons(): bool
    {
        return $this !== self::CROSS_LIST;
    }

    /**
     * The accepted string values, for validation (`in:` rule).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
