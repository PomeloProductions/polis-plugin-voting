<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Services;

/**
 * Ranking-level confidence: how complete and how stable a user's ranking of a
 * list of items is.
 *
 * Pure arithmetic over scalar counts and an array of recent per-vote score
 * deltas. Entity-agnostic.
 */
final class PlacementConfidenceCalculator
{
    /** A per-vote score delta below this fraction counts as a "stable" vote. */
    public const STABILITY_CHANGE_THRESHOLD = 0.0225;

    /** This many consecutive stable votes reaches 100% stability. */
    public const STABILITY_TARGET_STREAK = 5;

    /**
     * @param  int  $totalItems  Number of items in the ranking list.
     * @param  int|float  $sumTotalComparisons  Sum of `total_comparisons` across every
     *                                          item. Each comparison is counted twice
     *                                          here (once per participant), so it is
     *                                          halved internally.
     * @param  array<int, mixed>  $scoreChanges  Recent per-vote score deltas, oldest first.
     *                                           A non-numeric entry resets the streak.
     * @return array{
     *     overall: float,
     *     coverage: float,
     *     coverage_percent: float,
     *     stability: float,
     *     score_streak: int,
     *     total_items: int,
     *     total_comparisons: int,
     *     total_possible_pairs: int
     * }
     */
    public static function calculate(int $totalItems, int|float $sumTotalComparisons, array $scoreChanges): array
    {
        $totalPossiblePairs = $totalItems > 1 ? ($totalItems * ($totalItems - 1)) / 2 : 0;
        $totalComparisons = $sumTotalComparisons / 2;

        $rawCoveragePercent = $totalPossiblePairs > 0 ? ($totalComparisons / $totalPossiblePairs) * 100 : 0;
        $rawCoveragePercent = min(100, $rawCoveragePercent);

        $scoreStreak = 0;
        foreach ($scoreChanges as $change) {
            if (is_numeric($change) && $change < self::STABILITY_CHANGE_THRESHOLD) {
                $scoreStreak++;
            } else {
                $scoreStreak = 0;
            }
        }

        $stabilityPercent = min(100, ($scoreStreak / self::STABILITY_TARGET_STREAK) * 100);

        return [
            'overall' => round($rawCoveragePercent, 4),
            'coverage' => round($rawCoveragePercent, 4),
            'coverage_percent' => round($rawCoveragePercent, 4),
            'stability' => round($stabilityPercent, 2),
            'score_streak' => $scoreStreak,
            'total_items' => $totalItems,
            'total_comparisons' => (int) $totalComparisons,
            'total_possible_pairs' => (int) $totalPossiblePairs,
        ];
    }
}
