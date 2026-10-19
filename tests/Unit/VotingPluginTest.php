<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Unit;

use Polis\Plugin\Voting\Contracts\VotingEngineContract;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugin\Voting\Tests\TestCase;
use Polis\Plugin\Voting\VotingPlugin;
use Polis\Plugins\DefaultPluginRegistrar;
use Polis\Plugins\PluginContract;

/**
 * Proves the plugin satisfies the polis-laravel PluginContract and declares the
 * capabilities a consumer's PluginManager will apply.
 */
final class VotingPluginTest extends TestCase
{
    public function test_implements_the_polis_plugin_contract(): void
    {
        $this->assertInstanceOf(PluginContract::class, new VotingPlugin);
    }

    public function test_identity_metadata(): void
    {
        $plugin = new VotingPlugin;

        $this->assertSame('voting', $plugin->key());
        $this->assertSame('Voting', $plugin->name());
        $this->assertSame(VotingPlugin::VERSION, $plugin->version());
        $this->assertContains('system:list_ranking', $plugin->capabilities());
        $this->assertContains('system:cross_list', $plugin->capabilities());
        $this->assertContains('system:decision_helper', $plugin->capabilities());
    }

    public function test_register_declares_migrations_routes_binding_and_config(): void
    {
        $registrar = new DefaultPluginRegistrar('voting');
        (new VotingPlugin)->register($registrar);

        // Migration directory declared and real.
        $migrations = $registrar->getMigrations();
        $this->assertCount(1, $migrations);
        $this->assertDirectoryExists($migrations[0]);

        // Route file declared and real.
        $routes = $registrar->getRoutes();
        $this->assertCount(1, $routes);
        $this->assertFileExists($routes[0]['path']);
        $this->assertSame('voting', $routes[0]['opts']['prefix']);

        // Engine binding declared (consumer-override-aware pair).
        $bindings = $registrar->getBindings();
        $this->assertArrayHasKey(VotingEngineContract::class, $bindings);
        $this->assertSame(HeadToHeadVotingService::class, $bindings[VotingEngineContract::class][1]);

        // Namespaced default config declared.
        $this->assertArrayHasKey('defaults', $registrar->getConfig());
    }
}
