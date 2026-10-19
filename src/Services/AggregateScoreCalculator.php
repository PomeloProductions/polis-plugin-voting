<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Services;

/**
 * Pure arithmetic for an item's aggregate (win-rate) score.
 *
 * Entity-agnostic: operates only on integer win/total counts and float scores.
 * No database, no model coupling.
 */
final class AggregateScoreCalculator
{
    /**
     * The aggregate score for an item: its win rate across all comparisons.
     *
     * @param  int  $wins  Number of comparisons this item won.
     * @param  int  $total  Total comparisons this item took part in.
     * @return float Win rate in [0.0, 1.0], or 0.0 when there are no comparisons.
     */
    public static function score(int $wins, int $total): float
    {
        return $total > 0 ? $wins / $total : 0.0;
    }

    /**
     * The magnitude of a score change between two votes.
     *
     * The first time an item moves onto the scale (it had no prior comparisons)
     * the change is treated as maximal (1.0) so it registers as significant;
     * otherwise it is the absolute delta.
     *
     * @return float Change magnitude in [0.0, 1.0].
     */
    public static function scoreChange(float $beforeScore, float $afterScore, bool $hadComparisons): float
    {
        return $hadComparisons ? abs($afterScore - $beforeScore) : 1.0;
    }
}
