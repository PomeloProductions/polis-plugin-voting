<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Services;

/**
 * Canonical, order-independent key for a pair of item ids.
 *
 * `for(3, 7)` and `for(7, 3)` both yield "3_7", so a pair can be de-duplicated
 * regardless of the order the two items were presented in. Entity-agnostic: the
 * ids are plain integers (the engine's internal item-id handles).
 */
final class ComparisonPairKey
{
    public static function for(int $a, int $b): string
    {
        return min($a, $b).'_'.max($a, $b);
    }
}
