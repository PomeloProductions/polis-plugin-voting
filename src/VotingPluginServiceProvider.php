<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting;

use Illuminate\Support\ServiceProvider;
use Polis\Plugin\Voting\Contracts\VotingEngineContract;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;

/**
 * Package service provider for the Voting plugin.
 *
 * Auto-registered via composer's `extra.laravel.providers`, so requiring the
 * package activates it. Its responsibilities are intentionally narrow: expose
 * the package's default config for publishing and provide a FALLBACK binding
 * for the engine so the service resolves even in a consumer that uses the
 * engine directly without listing the plugin in `config('plugins.enabled')`.
 *
 * The canonical activation path — routes, migrations, the override-aware engine
 * binding, and namespaced plugin config — flows through {@see VotingPlugin}'s
 * {@see VotingPlugin::register()} applied by the polis-laravel PluginManager
 * when a consumer enables the plugin. This provider does not duplicate that
 * application; it only guarantees the engine is resolvable and the config
 * publishable.
 */
final class VotingPluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom($this->configPath(), 'plugin.voting.defaults');

        // Fallback binding. The plugin's register() installs an
        // override-aware binding (consumer App\... wins); this ensures the
        // contract always resolves even without the plugin enabled.
        if (! $this->app->bound(VotingEngineContract::class)) {
            $this->app->bind(VotingEngineContract::class, HeadToHeadVotingService::class);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                $this->configPath() => $this->app->configPath('plugin/voting.php'),
            ], 'polis-plugin-voting-config');
        }
    }

    private function configPath(): string
    {
        return dirname(__DIR__).'/config/voting.php';
    }
}
