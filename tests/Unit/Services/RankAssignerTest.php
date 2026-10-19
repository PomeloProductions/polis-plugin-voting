<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Polis\Plugin\Voting\Services\RankAssigner;

final class RankAssignerTest extends TestCase
{
    /**
     * @param  array{id: int, aggregate_score: float, total_comparisons: int}  $attrs
     */
    private function row(array $attrs): object
    {
        return (object) $attrs;
    }

    public function test_assigns_sequential_ranks_when_all_distinct(): void
    {
        $rows = [
            $this->row(['id' => 10, 'aggregate_score' => 0.9, 'total_comparisons' => 5]),
            $this->row(['id' => 20, 'aggregate_score' => 0.6, 'total_comparisons' => 5]),
            $this->row(['id' => 30, 'aggregate_score' => 0.3, 'total_comparisons' => 5]),
        ];

        $this->assertSame([10 => 1, 20 => 2, 30 => 3], RankAssigner::assignWithTies($rows));
    }

    public function test_uses_standard_competition_ranking_for_ties(): void
    {
        // Two rows tie only when BOTH score AND total_comparisons match.
        $rows = [
            $this->row(['id' => 1, 'aggregate_score' => 0.9, 'total_comparisons' => 4]),
            $this->row(['id' => 2, 'aggregate_score' => 0.5, 'total_comparisons' => 4]),
            $this->row(['id' => 3, 'aggregate_score' => 0.5, 'total_comparisons' => 4]),
            $this->row(['id' => 4, 'aggregate_score' => 0.1, 'total_comparisons' => 4]),
        ];

        // 1, 2, 2, 4 — the two tied rows share rank 2, next jumps to 4.
        $this->assertSame([1 => 1, 2 => 2, 3 => 2, 4 => 4], RankAssigner::assignWithTies($rows));
    }

    public function test_same_score_but_different_comparison_count_does_not_tie(): void
    {
        $rows = [
            $this->row(['id' => 1, 'aggregate_score' => 0.5, 'total_comparisons' => 10]),
            $this->row(['id' => 2, 'aggregate_score' => 0.5, 'total_comparisons' => 2]),
        ];

        $this->assertSame([1 => 1, 2 => 2], RankAssigner::assignWithTies($rows));
    }

    public function test_empty_input(): void
    {
        $this->assertSame([], RankAssigner::assignWithTies([]));
    }
}
