<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Polis\Plugin\Voting\Services\ScoreProximityWeighting;

final class ScoreProximityWeightingTest extends TestCase
{
    public function test_normalized_diff_clamps_and_normalizes(): void
    {
        $this->assertSame(0.0, ScoreProximityWeighting::normalizedDiff(0.0));
        $this->assertEqualsWithDelta(0.5, ScoreProximityWeighting::normalizedDiff(0.10), 1e-9);
        $this->assertSame(1.0, ScoreProximityWeighting::normalizedDiff(0.20));
        $this->assertSame(1.0, ScoreProximityWeighting::normalizedDiff(0.95));
    }

    public function test_proximity_weight_favours_close_scores(): void
    {
        // Identical -> 8^1 = 8.0 ; cap-or-beyond -> 8^0 = 1.0.
        $this->assertEqualsWithDelta(8.0, ScoreProximityWeighting::proximityWeight(0.5, 0.5), 1e-9);
        $this->assertEqualsWithDelta(1.0, ScoreProximityWeighting::proximityWeight(0.0, 0.5), 1e-9);
        $this->assertGreaterThan(
            ScoreProximityWeighting::proximityWeight(0.5, 0.3),
            ScoreProximityWeighting::proximityWeight(0.5, 0.45),
        );
    }

    public function test_low_comparison_boost(): void
    {
        $this->assertSame(1.0, ScoreProximityWeighting::lowComparisonBoost(3, 3, 3));
        $this->assertEqualsWithDelta(1.0, ScoreProximityWeighting::lowComparisonBoost(5, 0, 5), 1e-9);
        $this->assertEqualsWithDelta(100.0, ScoreProximityWeighting::lowComparisonBoost(3, 0, 5), 1e-9);
    }

    public function test_revote_staleness_weight(): void
    {
        $this->assertSame(16.0, ScoreProximityWeighting::revoteStalenessWeight(null));
        $this->assertEqualsWithDelta(1.0, ScoreProximityWeighting::revoteStalenessWeight(1.0), 1e-9);
        $this->assertEqualsWithDelta(1.0, ScoreProximityWeighting::revoteStalenessWeight(0.5), 1e-9); // floored at 1 day
        $this->assertEqualsWithDelta(1.0 + log(30, 2), ScoreProximityWeighting::revoteStalenessWeight(30.0), 1e-9);
    }

    public function test_revote_proximity_weight_range(): void
    {
        $this->assertEqualsWithDelta(2.0, ScoreProximityWeighting::revoteProximityWeight(0.5, 0.5), 1e-9);
        $this->assertEqualsWithDelta(1.0, ScoreProximityWeighting::revoteProximityWeight(0.0, 0.5), 1e-9);
    }

    public function test_pair_freshness_weight(): void
    {
        $this->assertEqualsWithDelta(1.0, ScoreProximityWeighting::pairFreshnessWeight(0), 1e-9);
        $this->assertEqualsWithDelta(0.5, ScoreProximityWeighting::pairFreshnessWeight(20), 1e-9);
        $this->assertEqualsWithDelta(1 / 3, ScoreProximityWeighting::pairFreshnessWeight(40), 1e-9);
    }

    public function test_pick_by_cumulative_weight(): void
    {
        $weights = ['a' => 1.0, 'b' => 2.0, 'c' => 3.0];

        $this->assertSame('a', ScoreProximityWeighting::pickByCumulativeWeight($weights, 0.5));
        $this->assertSame('b', ScoreProximityWeighting::pickByCumulativeWeight($weights, 2.0));
        $this->assertSame('c', ScoreProximityWeighting::pickByCumulativeWeight($weights, 5.0));
        // Beyond the total -> last key fallback.
        $this->assertSame('c', ScoreProximityWeighting::pickByCumulativeWeight($weights, 99.0));
    }

    public function test_pick_by_cumulative_weight_empty_is_null(): void
    {
        $this->assertNull(ScoreProximityWeighting::pickByCumulativeWeight([], 0.5));
    }
}
