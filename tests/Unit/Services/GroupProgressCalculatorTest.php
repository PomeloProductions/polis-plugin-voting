<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Polis\Plugin\Voting\Services\GroupProgressCalculator;

final class GroupProgressCalculatorTest extends TestCase
{
    private GroupProgressCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new GroupProgressCalculator;
    }

    public function test_coverage_percent(): void
    {
        // 10 items -> 9 possible comparisons; 9 votes = 100%.
        $this->assertEqualsWithDelta(100.0, $this->calc->calculateCoveragePercent(9, 10), 1e-9);
        $this->assertEqualsWithDelta(50.0, $this->calc->calculateCoveragePercent(1, 3), 1e-9);
    }

    public function test_minimum_votes(): void
    {
        $this->assertSame(5, $this->calc->calculateMinimumVotes(3));
        $this->assertSame(9, $this->calc->calculateMinimumVotes(10));
    }

    public function test_stability_rate_caps_at_one(): void
    {
        $this->assertEqualsWithDelta(0.4, $this->calc->calculateStabilityRate(2), 1e-9);
        $this->assertSame(1.0, $this->calc->calculateStabilityRate(5));
        $this->assertSame(1.0, $this->calc->calculateStabilityRate(10));
    }

    public function test_window_completion(): void
    {
        $this->assertEqualsWithDelta(0.8, $this->calc->calculateWindowCompletion(2, 10), 1e-9);
        $this->assertSame(1.0, $this->calc->calculateWindowCompletion(0, 10));
        $this->assertSame(0.0, $this->calc->calculateWindowCompletion(5, 0));
    }

    public function test_vote_multiplier(): void
    {
        $this->assertEqualsWithDelta(0.4, $this->calc->calculateVoteMultiplier(2), 1e-9);
        $this->assertSame(1.0, $this->calc->calculateVoteMultiplier(5));
        $this->assertSame(1.0, $this->calc->calculateVoteMultiplier(50));
    }

    public function test_max_fill_factor(): void
    {
        $this->assertSame(0.0, $this->calc->calculateMaxFillFactor(5));
        $this->assertEqualsWithDelta(1 / 3, $this->calc->calculateMaxFillFactor(10), 1e-9);
        $this->assertSame(1.0, $this->calc->calculateMaxFillFactor(20));
        $this->assertSame(1.0, $this->calc->calculateMaxFillFactor(25));
    }

    public function test_single_item_group_is_complete(): void
    {
        $this->assertSame(100.0, $this->calc->calculateProgress(0, 1, 0, 0));
    }

    public function test_progress_is_monotonic_and_bounded(): void
    {
        $p5 = $this->calc->calculateProgress(5, 100, 0, 100);
        $p20 = $this->calc->calculateProgress(20, 100, 0, 100);

        $this->assertGreaterThan($p5, $p20);
        $this->assertLessThanOrEqual(100.0, $p20);
        $this->assertGreaterThanOrEqual(0.0, $p5);
    }

    public function test_lock_rules(): void
    {
        // Single item -> always lock.
        $this->assertTrue($this->calc->shouldLockGroup(0, 1, 0, 0, false, false));
        // All compared -> lock.
        $this->assertTrue($this->calc->shouldLockGroup(3, 10, 0, 5, false, true));
        // Hard cap -> lock.
        $this->assertTrue($this->calc->shouldLockGroup(20, 100, 0, 50, false, false));
        // Below minimum -> never lock.
        $this->assertFalse($this->calc->shouldLockGroup(3, 10, 5, 0, false, false));
        // Past minimum + stable streak -> lock.
        $this->assertTrue($this->calc->shouldLockGroup(6, 10, 5, 3, false, false));
        // Past minimum + empty non-point window -> lock.
        $this->assertTrue($this->calc->shouldLockGroup(6, 10, 0, 0, false, false));
        // Past minimum + point window (ignored) + no stability -> no lock.
        $this->assertFalse($this->calc->shouldLockGroup(6, 10, 0, 0, true, false));
    }
}
