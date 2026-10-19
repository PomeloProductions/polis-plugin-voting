<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Services;

/**
 * Pure weighting functions for SMART PAIR-SELECTION.
 *
 * The engine builds a weight per candidate opponent and draws one by cumulative
 * weight. These functions supply the individual weight factors. All are pure
 * arithmetic over scalars — entity-agnostic, no DB, no model coupling.
 */
final class ScoreProximityWeighting
{
    /** Score differences at or above this fraction are treated uniformly. */
    public const DIFF_CAP = 0.20;

    /**
     * Normalise a score difference to [0.0, 1.0]: 0 at identical scores, 1 at or
     * beyond the cap.
     */
    public static function normalizedDiff(float $scoreDiff): float
    {
        return min($scoreDiff, self::DIFF_CAP) / self::DIFF_CAP;
    }

    /**
     * Normal-mode proximity weight: strongly favour opponents with a SIMILAR
     * score, so close calls get compared more.
     *
     *   weight = 8 ^ (1 - normalizedDiff)
     *   identical scores -> 8.0 ; cap-or-beyond -> 1.0 (baseline)
     */
    public static function proximityWeight(float $focusScore, float $candidateScore): float
    {
        $normalizedDiff = self::normalizedDiff(abs($focusScore - $candidateScore));

        return pow(8, 1 - $normalizedDiff);
    }

    /**
     * Boost candidates that have been compared FEWER times, to balance exposure.
     *
     *   multiplier = 10 ^ (maxComparisons - comparisons)  (1.0 when no spread)
     */
    public static function lowComparisonBoost(int $comparisons, int $minComparisons, int $maxComparisons): float
    {
        if ($maxComparisons <= $minComparisons) {
            return 1.0;
        }

        $gap = $maxComparisons - $comparisons;

        return pow(10, $gap);
    }

    /**
     * Revote staleness weight: prioritise pairs not compared in a long time.
     *
     *   never compared -> 16.0 (highest) ; else 1 + log2(max(1, days))
     */
    public static function revoteStalenessWeight(?float $daysSinceLastComparison): float
    {
        if ($daysSinceLastComparison === null) {
            return 16.0;
        }

        $days = max(1.0, $daysSinceLastComparison);

        return 1.0 + log($days, 2);
    }

    /**
     * Secondary (weaker) proximity weight used in revote mode: range [1.0, 2.0].
     *
     *   weight = 1 + (1 - normalizedDiff)
     */
    public static function revoteProximityWeight(float $focusScore, float $candidateScore): float
    {
        $normalizedDiff = self::normalizedDiff(abs($focusScore - $candidateScore));

        return 1.0 + (1.0 - $normalizedDiff);
    }

    /**
     * Favour fresh pairings: the more the two items have been compared overall,
     * the lower the weight.
     *
     *   weight = 1 / (1 + pairComparisonSum / 20)
     */
    public static function pairFreshnessWeight(int $pairComparisonSum): float
    {
        return 1.0 / (1.0 + $pairComparisonSum / 20.0);
    }

    /**
     * Deterministic weighted-random selection.
     *
     * Walks the weight map accumulating; returns the first key whose running
     * total reaches $random. The caller must draw $random uniformly from
     * [0, sum($weights)]. Returns null for an empty map; the last key as a
     * floating-point-safety fallback.
     *
     * @param  array<int|string, float>  $weights
     * @return int|string|null
     */
    public static function pickByCumulativeWeight(array $weights, float $random)
    {
        if ($weights === []) {
            return null;
        }

        $cumulative = 0.0;
        foreach ($weights as $key => $weight) {
            $cumulative += $weight;
            if ($random <= $cumulative) {
                return $key;
            }
        }

        return array_key_last($weights);
    }
}
