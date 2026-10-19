<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit\Enums;

use PHPUnit\Framework\TestCase;
use Polis\Plugin\Voting\Enums\VotingSystem;

final class VotingSystemTest extends TestCase
{
    public function test_persistence_semantics(): void
    {
        $this->assertTrue(VotingSystem::LIST_RANKING->isPersistent());
        $this->assertTrue(VotingSystem::CROSS_LIST->isPersistent());
        $this->assertFalse(VotingSystem::DECISION_HELPER->isPersistent());
    }

    public function test_same_list_comparison_semantics(): void
    {
        $this->assertTrue(VotingSystem::LIST_RANKING->allowsSameListComparisons());
        $this->assertFalse(VotingSystem::CROSS_LIST->allowsSameListComparisons());
        $this->assertTrue(VotingSystem::DECISION_HELPER->allowsSameListComparisons());
    }

    public function test_values_lists_all_cases(): void
    {
        $this->assertSame(
            ['list_ranking', 'cross_list', 'decision_helper'],
            VotingSystem::values(),
        );
    }
}
