<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Polis\Plugin\Voting\VotingPluginServiceProvider;

/**
 * Base test case for the standalone Unit suite.
 *
 * Backed by Orchestra Testbench so the plugin's pure calculators and plugin
 * wiring can be exercised without a consuming Laravel application. The pure
 * calculator tests do not touch the container at all; this base exists so any
 * Unit test that does need the provider (e.g. plugin-contract assertions) can
 * boot it in isolation.
 */
abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            VotingPluginServiceProvider::class,
        ];
    }
}
