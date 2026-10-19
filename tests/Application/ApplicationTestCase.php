<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Application;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Polis\Plugin\Voting\VotingPlugin;
use Polis\Plugin\Voting\VotingPluginServiceProvider;
use Polis\Plugins\PluginManager;
use Polis\Plugins\PluginServiceProvider;

/**
 * Base for the Application (Feature/Integration) suites.
 *
 * Boots the plugin the way a real consumer does: the voting plugin is listed in
 * config('plugins.enabled'), and the polis-laravel PluginServiceProvider (the
 * same provider a consumer gets) discovers it and applies its routes + loads its
 * migrations. The dummy consumer app under tests/Application supplies the owner
 * (User) and an arbitrary votable entity (Candidate) so the engine is exercised
 * against host-owned entities, exactly as it will be in production.
 */
abstract class ApplicationTestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            // The plugin's own provider (fallback engine binding + config).
            VotingPluginServiceProvider::class,
            // The polis-laravel plugin manager provider — discovers enabled
            // plugins and applies routes/migrations/bindings.
            PluginServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $config = $app->make('config');

        $config->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Enable the voting plugin, exactly as a consumer app would. Set BEFORE
        // the providers boot so the PluginManager discovers it when it applies
        // bindings/routes/migrations.
        $config->set('plugins.enabled', [VotingPlugin::class]);

        // The plugin registers its routes under the "api-v1" middleware group;
        // register a minimal group so Testbench can resolve it.
        $app->make('router')->middlewareGroup('api-v1', [
            SubstituteBindings::class,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Testbench's provider boot runs before getEnvironmentSetUp populates
        // plugins.enabled in some lifecycles, so the PluginManager can cache an
        // empty discovery. Reset it now (config is definitely set) and re-apply
        // the plugin's route groups so the endpoints are registered for the
        // Feature suite. (Migrations are loaded explicitly in
        // defineDatabaseMigrations.)
        $manager = $this->app->make(PluginManager::class);
        $manager->reset();
        $manager->applyRoutes();
        Route::getRoutes()->refreshNameLookups();
        Route::getRoutes()->refreshActionLookups();
    }

    protected function defineDatabaseMigrations(): void
    {
        // Dummy consumer app schema (owner + votable entity).
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
        // The voting plugin's own migrations. In production these auto-load via
        // the PluginManager; loading them explicitly here is deterministic for
        // the in-memory sqlite test DB.
        $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations');
    }
}
