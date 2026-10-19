<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Services;

/**
 * Per-list convergence / progress calculation and the lock decision for a
 * ranking "pass" over a list of items.
 *
 * ("Group" is the engine's internal term for one convergence window over a set
 * of candidate items; a consumer's notion of a "list" maps onto it.) All pure
 * arithmetic over scalar counts — entity-agnostic, no DB, no model coupling.
 */
final class GroupProgressCalculator
{
    /** Consecutive stable placements that count as "converged". */
    public const REQUIRED_STABLE_PLACEMENTS = 5;

    /** Minimum votes before a list may lock. */
    public const ABSOLUTE_MINIMUM_VOTES = 5;

    /** Hard cap: a list always locks at this many votes. */
    public const ABSOLUTE_MAXIMUM_VOTES = 20;

    /** coverage = (votes / (count - 1)) * 100 */
    public function calculateCoveragePercent(int $votesCast, int $groupCount): float
    {
        $possibleComparisons = max(1, $groupCount - 1);

        return ($votesCast / $possibleComparisons) * 100;
    }

    /** minimum = max(5, count - 1) */
    public function calculateMinimumVotes(int $groupCount): int
    {
        return max(self::ABSOLUTE_MINIMUM_VOTES, $groupCount - 1);
    }

    /** stability = min(1.0, streak / 5) */
    public function calculateStabilityRate(int $placementStreak): float
    {
        return min(1.0, $placementStreak / self::REQUIRED_STABLE_PLACEMENTS);
    }

    /** window_completion = max(0, 1 - candidatesInWindow / count) */
    public function calculateWindowCompletion(int $candidatesInWindow, int $groupCount): float
    {
        if ($groupCount <= 0) {
            return 0.0;
        }

        return max(0.0, 1.0 - ($candidatesInWindow / $groupCount));
    }

    /** multiplier = min(votes, 5) / 5 */
    public function calculateVoteMultiplier(int $votesCast): float
    {
        return min($votesCast, self::ABSOLUTE_MINIMUM_VOTES) / self::ABSOLUTE_MINIMUM_VOTES;
    }

    /** max_fill = clamp((votes - 5) / (20 - 5), 0, 1) */
    public function calculateMaxFillFactor(int $votesCast): float
    {
        $range = self::ABSOLUTE_MAXIMUM_VOTES - self::ABSOLUTE_MINIMUM_VOTES;
        if ($range <= 0) {
            return 0.0;
        }

        $votesPastMin = $votesCast - self::ABSOLUTE_MINIMUM_VOTES;

        return max(0.0, min(1.0, $votesPastMin / $range));
    }

    /**
     * Overall progress percentage [0, 100] via a two-layer formula:
     *   layer 1 (confidence): coverage lifted by stability/window * vote multiplier
     *   layer 2 (fill):       remaining space filled as votes approach the max
     */
    public function calculateProgress(
        int $votesCast,
        int $groupCount,
        int $placementStreak,
        int $candidatesInWindow,
        bool $isPointWindow = false
    ): float {
        if ($groupCount <= 1) {
            return 100.0;
        }

        $coverage = $this->calculateCoveragePercent($votesCast, $groupCount);
        $stabilityRate = $this->calculateStabilityRate($placementStreak);
        $windowCompletion = $isPointWindow ? 0.0 : $this->calculateWindowCompletion($candidatesInWindow, $groupCount);
        $voteMultiplier = $this->calculateVoteMultiplier($votesCast);

        // Layer 1: confidence from coverage + stability/window.
        $stabilityFactor = max($stabilityRate, $windowCompletion);
        $remainingPercent = 1 - ($coverage / 100);
        $confidence = $coverage + ($remainingPercent * $stabilityFactor * 100 * $voteMultiplier);

        // Layer 2: fill remaining empty space based on progress toward max votes.
        $maxFillFactor = $this->calculateMaxFillFactor($votesCast);
        $remainingAfterConfidence = 100 - $confidence;
        $progress = $confidence + ($remainingAfterConfidence * $maxFillFactor);

        return min(100.0, $progress);
    }

    /**
     * Whether a list has converged enough to lock.
     *
     * Locks when: only one item; every item compared; the hard vote cap is hit;
     * or (past the minimum) the placement streak is met OR a non-point score
     * window has emptied. Never locks below the minimum.
     */
    public function shouldLockGroup(
        int $votesCast,
        int $groupCount,
        int $placementStreak,
        int $candidatesInWindow,
        bool $isPointWindow,
        bool $allItemsCompared
    ): bool {
        if ($groupCount <= 1) {
            return true;
        }

        if ($allItemsCompared) {
            return true;
        }

        if ($votesCast >= self::ABSOLUTE_MAXIMUM_VOTES) {
            return true;
        }

        if ($votesCast < self::ABSOLUTE_MINIMUM_VOTES) {
            return false;
        }

        $stabilityAchieved = $placementStreak >= self::REQUIRED_STABLE_PLACEMENTS;
        $windowConverged = ! $isPointWindow && $candidatesInWindow === 0;

        return $stabilityAchieved || $windowConverged;
    }
}
