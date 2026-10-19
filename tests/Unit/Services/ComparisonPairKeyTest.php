<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Polis\Plugin\Voting\Services\ComparisonPairKey;

final class ComparisonPairKeyTest extends TestCase
{
    public function test_key_is_order_independent(): void
    {
        $this->assertSame('3_7', ComparisonPairKey::for(3, 7));
        $this->assertSame('3_7', ComparisonPairKey::for(7, 3));
    }

    public function test_equal_ids_produce_symmetric_key(): void
    {
        $this->assertSame('5_5', ComparisonPairKey::for(5, 5));
    }
}
