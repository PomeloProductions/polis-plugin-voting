<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Support;

/**
 * Resolves the plugin's table names, honouring an optional
 * `config('plugin.voting.defaults.table_prefix')` so a host app that already
 * owns a `voting_sessions` table can namespace the plugin's tables and avoid a
 * collision.
 *
 * Kept as a static helper (not a model concern) so both migrations and models
 * resolve the SAME name from one place.
 */
final class VotingTables
{
    public const SESSIONS = 'voting_sessions';

    public const COMPARISONS = 'voting_comparisons';

    public const ITEM_RANKINGS = 'item_rankings';

    public static function prefix(): string
    {
        // Resolve defensively: migrations may run before the config facade is
        // conveniently available in every context.
        if (function_exists('config')) {
            $prefix = config('plugin.voting.defaults.table_prefix');
            if (is_string($prefix)) {
                return $prefix;
            }
        }

        return '';
    }

    public static function sessions(): string
    {
        return self::prefix().self::SESSIONS;
    }

    public static function comparisons(): string
    {
        return self::prefix().self::COMPARISONS;
    }

    public static function itemRankings(): string
    {
        return self::prefix().self::ITEM_RANKINGS;
    }
}
