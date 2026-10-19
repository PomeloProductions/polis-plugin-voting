<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Feature;

use App\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Models\ItemRanking;
use Polis\Plugin\Voting\Models\VotingComparison;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugin\Voting\Support\VotableItem;
use Polis\Plugin\Voting\Tests\Application\ApplicationTestCase;

/**
 * POST /voting/sessions/{session}/vote — cast a vote.
 */
final class VotingSessionVoteTest extends ApplicationTestCase
{
    use RefreshDatabase;

    private int $sessionId;

    private VotableItem $a;

    private VotableItem $b;

    protected function setUp(): void
    {
        parent::setUp();

        $ca = Candidate::create(['title' => 'A']);
        $cb = Candidate::create(['title' => 'B']);
        $this->a = new VotableItem(Candidate::class, $ca->id);
        $this->b = new VotableItem(Candidate::class, $cb->id);

        $session = (new HeadToHeadVotingService)->startSession(
            'user', 1, VotingSystem::LIST_RANKING, 'my_list', [$this->a, $this->b],
        );
        $this->sessionId = $session->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'winner' => ['type' => $this->a->type, 'id' => $this->a->id],
            'loser' => ['type' => $this->b->type, 'id' => $this->b->id],
        ];
    }

    public function test_casts_a_vote_successfully(): void
    {
        $this->postJson("voting/sessions/{$this->sessionId}/vote", $this->validPayload())
            ->assertOk()
            ->assertJsonStructure(['session', 'next_comparison', 'progress']);

        $this->assertSame(1, VotingComparison::query()->count());
        $this->assertSame(1, ItemRanking::query()->where('votable_id', $this->a->id)->firstOrFail()->wins);
    }

    public function test_winner_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['winner']);

        $this->postJson("voting/sessions/{$this->sessionId}/vote", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('winner');
    }

    public function test_loser_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['loser']);

        $this->postJson("voting/sessions/{$this->sessionId}/vote", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('loser');
    }

    public function test_winner_type_and_id_are_required(): void
    {
        $payload = $this->validPayload();
        $payload['winner'] = [];

        $this->postJson("voting/sessions/{$this->sessionId}/vote", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['winner.type', 'winner.id']);
    }

    public function test_winner_id_must_be_integer(): void
    {
        $payload = $this->validPayload();
        $payload['winner']['id'] = 'nope';

        $this->postJson("voting/sessions/{$this->sessionId}/vote", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('winner.id');
    }

    public function test_first_shown_requires_type_and_id_when_present(): void
    {
        $payload = $this->validPayload();
        $payload['first_shown'] = ['id' => 5];

        $this->postJson("voting/sessions/{$this->sessionId}/vote", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('first_shown.type');
    }

    public function test_unknown_session_is_404(): void
    {
        $this->postJson('voting/sessions/999999/vote', $this->validPayload())
            ->assertNotFound();
    }
}
