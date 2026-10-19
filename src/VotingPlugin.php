<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting;

use Polis\Plugin\Voting\Contracts\VotingEngineContract;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugins\PluginContract;
use Polis\Plugins\PluginManager;
use Polis\Plugins\PluginRegistrar;

/**
 * The Polis Voting plugin.
 *
 * A consumer app opts in by listing this class in `config('plugins.enabled')`;
 * the polis-laravel {@see PluginManager} then calls
 * {@see register()} once and applies the declared capabilities. No provider
 * hand-wiring is required.
 *
 * This plugin contributes a self-contained, ENTITY-AGNOSTIC head-to-head
 * (pairwise) ranking engine. It implements ONE comparison mechanism that
 * powers THREE user-selectable systems, chosen per session via a REQUIRED
 * `voting_type` + `system`:
 *
 *  1. LIST_RANKING          — persistent pairwise ranking of items within one
 *                             list; same-list comparisons allowed.
 *  2. CROSS_LIST            — rank the top items ACROSS lists; only cross-list
 *                             comparisons; depends on list-ranking scores.
 *  3. DECISION_HELPER       — ephemeral "what to pick" ranking, cleared each
 *                             session.
 *
 * The engine knows nothing about the domain of the things it ranks: an item is
 * identified by a generic polymorphic pair `(votable_type, votable_id)` and an
 * optional `list_id`. See the package README for the data model.
 */
final class VotingPlugin implements PluginContract
{
    public const KEY = 'voting';

    public const VERSION = '0.1.0';

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return 'Voting';
    }

    public function version(): string
    {
        return self::VERSION;
    }

    /**
     * @return array<int, string>
     */
    public function capabilities(): array
    {
        return [
            'routes',
            'migrations',
            'bindings',
            'config',
            'system:list_ranking',
            'system:cross_list',
            'system:decision_helper',
        ];
    }

    public function register(PluginRegistrar $registrar): void
    {
        $registrar
            ->bindings([
                // Consumer override wins (App\...), else the package engine.
                VotingEngineContract::class => [
                    \App\Services\Voting\HeadToHeadVotingService::class,
                    HeadToHeadVotingService::class,
                ],
            ])
            ->migrations(dirname(__DIR__).'/database/migrations')
            ->routes(dirname(__DIR__).'/routes/voting.php', [
                'prefix' => 'voting',
                'middleware' => 'api-v1',
            ])
            ->config('defaults', require dirname(__DIR__).'/config/voting.php');
    }
}
