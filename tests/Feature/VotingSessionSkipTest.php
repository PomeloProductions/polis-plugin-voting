<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Feature;

use App\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Polis\Plugin\Voting\Enums\VotingSystem;
use Polis\Plugin\Voting\Models\VotingComparison;
use Polis\Plugin\Voting\Services\HeadToHeadVotingService;
use Polis\Plugin\Voting\Support\VotableItem;
use Polis\Plugin\Voting\Tests\Application\ApplicationTestCase;

/**
 * POST /voting/sessions/{session}/skip — skip a comparison (no winner).
 */
final class VotingSessionSkipTest extends ApplicationTestCase
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

        $this->sessionId = (new HeadToHeadVotingService)
            ->startSession('user', 1, VotingSystem::LIST_RANKING, 'my_list', [$this->a, $this->b])->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'item_a' => ['type' => $this->a->type, 'id' => $this->a->id],
            'item_b' => ['type' => $this->b->type, 'id' => $this->b->id],
        ];
    }

    public function test_skips_successfully(): void
    {
        $this->postJson("voting/sessions/{$this->sessionId}/skip", $this->validPayload())
            ->assertOk()
            ->assertJsonStructure(['session', 'next_comparison', 'progress']);

        $this->assertSame(1, VotingComparison::query()->where('skipped', true)->count());
    }

    public function test_item_a_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['item_a']);

        $this->postJson("voting/sessions/{$this->sessionId}/skip", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('item_a');
    }

    public function test_item_b_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['item_b']);

        $this->postJson("voting/sessions/{$this->sessionId}/skip", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('item_b');
    }

    public function test_item_fields_are_required(): void
    {
        $payload = ['item_a' => ['id' => 1], 'item_b' => ['type' => Candidate::class]];

        $this->postJson("voting/sessions/{$this->sessionId}/skip", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['item_a.type', 'item_b.id']);
    }

    public function test_unknown_session_is_404(): void
    {
        $this->postJson('voting/sessions/999999/skip', $this->validPayload())->assertNotFound();
    }
}
