<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Polis\Plugin\Voting\Services\PlacementConfidenceCalculator;

final class PlacementConfidenceCalculatorTest extends TestCase
{
    public function test_coverage_is_comparisons_over_possible_pairs(): void
    {
        // 4 items -> 6 possible pairs. sumTotalComparisons=12 -> 6 comparisons
        // (halved) -> 100% coverage.
        $result = PlacementConfidenceCalculator::calculate(4, 12, []);

        $this->assertSame(6, $result['total_possible_pairs']);
        $this->assertSame(6, $result['total_comparisons']);
        $this->assertSame(100.0, $result['coverage']);
    }

    public function test_coverage_is_capped_at_100(): void
    {
        $result = PlacementConfidenceCalculator::calculate(3, 100, []);

        $this->assertSame(100.0, $result['coverage']);
    }

    public function test_zero_possible_pairs_yields_zero_coverage(): void
    {
        $result = PlacementConfidenceCalculator::calculate(1, 0, []);

        $this->assertSame(0, $result['total_possible_pairs']);
        $this->assertSame(0.0, $result['coverage']);
    }

    public function test_stability_counts_consecutive_small_changes(): void
    {
        // Five consecutive sub-threshold (<0.0225) changes -> 100% stability.
        $result = PlacementConfidenceCalculator::calculate(10, 0, [0.01, 0.01, 0.02, 0.0, 0.015]);

        $this->assertSame(5, $result['score_streak']);
        $this->assertSame(100.0, $result['stability']);
    }

    public function test_large_change_resets_stability_streak(): void
    {
        // A big change mid-sequence resets the streak; only the trailing run counts.
        $result = PlacementConfidenceCalculator::calculate(10, 0, [0.01, 0.9, 0.01, 0.01]);

        $this->assertSame(2, $result['score_streak']);
        $this->assertSame(40.0, $result['stability']);
    }

    public function test_non_numeric_change_resets_streak(): void
    {
        $result = PlacementConfidenceCalculator::calculate(10, 0, [0.01, 'x', 0.01]);

        $this->assertSame(1, $result['score_streak']);
    }
}
