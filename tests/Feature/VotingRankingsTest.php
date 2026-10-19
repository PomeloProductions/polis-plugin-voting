<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Feature;

use App\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugin\Voting\Support\VotableItem;
use Polis\Plugin\Voting\Tests\Application\ApplicationTestCase;

/**
 * GET /voting/rankings — the current rankings for an owner + voting_type.
 */
final class VotingRankingsTest extends ApplicationTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $items = [];
        foreach (['A', 'B'] as $t) {
            $c = Candidate::create(['title' => $t]);
            $items[] = new VotableItem(Candidate::class, $c->id);
        }
        $engine = new HeadToHeadVotingService;
        $session = $engine->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items);
        $engine->recordVote($session, $items[0], $items[1]);
    }

    public function test_returns_rankings_best_first(): void
    {
        $this->getJson('voting/rankings?'.http_build_query([
            'owner_type' => 'user',
            'owner_id' => 1,
            'voting_type' => 'my_list',
        ]))
            ->assertOk()
            ->assertJsonStructure(['rankings' => [['votable_id', 'wins', 'aggregate_score']]])
            ->assertJsonPath('rankings.0.wins', 1);
    }

    public function test_voting_type_is_required(): void
    {
        $this->getJson('voting/rankings?'.http_build_query([
            'owner_type' => 'user',
            'owner_id' => 1,
        ]))->assertStatus(422);
    }
}
