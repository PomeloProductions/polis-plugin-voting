<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Polis\Plugin\Voting\Services\AggregateScoreCalculator;

final class AggregateScoreCalculatorTest extends TestCase
{
    public function test_score_is_win_rate(): void
    {
        $this->assertSame(0.5, AggregateScoreCalculator::score(2, 4));
        $this->assertSame(1.0, AggregateScoreCalculator::score(3, 3));
        $this->assertSame(0.0, AggregateScoreCalculator::score(0, 5));
    }

    public function test_score_is_zero_with_no_comparisons(): void
    {
        $this->assertSame(0.0, AggregateScoreCalculator::score(0, 0));
        $this->assertSame(0.0, AggregateScoreCalculator::score(5, 0));
    }

    public function test_score_change_is_absolute_delta_when_had_comparisons(): void
    {
        $this->assertEqualsWithDelta(0.25, AggregateScoreCalculator::scoreChange(0.5, 0.75, true), 1e-9);
        $this->assertEqualsWithDelta(0.25, AggregateScoreCalculator::scoreChange(0.75, 0.5, true), 1e-9);
        $this->assertSame(0.0, AggregateScoreCalculator::scoreChange(0.4, 0.4, true));
    }

    public function test_score_change_is_maximal_on_first_move_onto_scale(): void
    {
        $this->assertSame(1.0, AggregateScoreCalculator::scoreChange(0.0, 0.9, false));
    }
}
