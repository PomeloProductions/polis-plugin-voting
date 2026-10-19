<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Feature;

use App\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Models\VotingSession;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugin\Voting\Support\VotableItem;
use Polis\Plugin\Voting\Tests\Application\ApplicationTestCase;

/**
 * POST /voting/sessions/{session}/complete — complete a session.
 */
final class VotingSessionCompleteTest extends ApplicationTestCase
{
    use RefreshDatabase;

    private int $sessionId;

    protected function setUp(): void
    {
        parent::setUp();

        $items = [];
        foreach (['A', 'B'] as $t) {
            $c = Candidate::create(['title' => $t]);
            $items[] = new VotableItem(Candidate::class, $c->id);
        }
        $this->sessionId = (new HeadToHeadVotingService)
            ->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', $items)->id;
    }

    public function test_completes_successfully(): void
    {
        $this->postJson("voting/sessions/{$this->sessionId}/complete")
            ->assertOk()
            ->assertJsonPath('session.status', VotingSession::STATUS_COMPLETED);

        $this->assertDatabaseHas((new VotingSession)->getTable(), [
            'id' => $this->sessionId,
            'status' => VotingSession::STATUS_COMPLETED,
        ]);
    }

    public function test_unknown_session_is_404(): void
    {
        $this->postJson('voting/sessions/999999/complete')->assertNotFound();
    }
}
