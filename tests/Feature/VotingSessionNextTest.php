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
 * GET /voting/sessions/{session}/next — fetch the next comparison.
 */
final class VotingSessionNextTest extends ApplicationTestCase
{
    use RefreshDatabase;

    private int $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $items = [];
        foreach (['A', 'B', 'C'] as $t) {
            $c = Candidate::create(['title' => $t]);
            $items[] = new VotableItem(Candidate::class, $c->id);
        }
        $this->sessionId = (new HeadToHeadVotingService)
            ->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items)->id;
    }

    public function test_returns_next_comparison(): void
    {
        $this->getJson("voting/sessions/{$this->sessionId}/next")
            ->assertOk()
            ->assertJsonStructure([
                'session',
                'next_comparison' => [['type', 'id'], ['type', 'id']],
                'progress' => ['votes_cast', 'estimated_completion', 'pool_size'],
            ]);
    }

    public function test_unknown_session_is_404(): void
    {
        $this->getJson('voting/sessions/999999/next')->assertNotFound();
    }
}
