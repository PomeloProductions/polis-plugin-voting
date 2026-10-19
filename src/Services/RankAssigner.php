<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Services;

/**
 * Assigns standard competition ranks (1, 1, 3, 4, 4, 6, ...) to an already
 * sorted sequence of ranking rows.
 *
 * Two rows tie — and therefore share a rank — only when BOTH their
 * `aggregate_score` AND their `total_comparisons` are identical, so a row with
 * the same score but more comparisons behind it is NOT tied with a less-tested
 * one. Entity-agnostic: reads three public properties (`id`, `aggregate_score`,
 * `total_comparisons`) from each element, so Eloquent models, stdClass rows and
 * test doubles all work.
 */
final class RankAssigner
{
    /**
     * @param  iterable<int, object{id: int|string, aggregate_score: float|int, total_comparisons: int}>  $sortedRankings
     *                                                                                                                     Rows pre-sorted best-first.
     * @return array<int|string, int> Map of each row's id => its 1-based rank.
     */
    public static function assignWithTies(iterable $sortedRankings): array
    {
        $ranks = [];
        $currentRank = 1;
        $index = 0;
        $previous = null;

        foreach ($sortedRankings as $ranking) {
            // $index > 0 guarantees $previous was set on a prior iteration.
            if (
                $index > 0
                && (
                    $ranking->aggregate_score !== $previous->aggregate_score
                    || $ranking->total_comparisons !== $previous->total_comparisons
                )
            ) {
                $currentRank = $index + 1;
            }

            $ranks[$ranking->id] = $currentRank;
            $previous = $ranking;
            $index++;
        }

        return $ranks;
    }
}
