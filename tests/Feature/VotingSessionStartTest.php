<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Tests\Feature;

use App\Models\Candidate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Polis\Plugin\Voting\Models\VotingSession;
use Polis\Plugin\Voting\Tests\Application\ApplicationTestCase;

/**
 * POST /voting/sessions — start a session. REQUIRED system + voting_type +
 * at least two items.
 */
final class VotingSessionStartTest extends ApplicationTestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        $a = Candidate::create(['title' => 'A']);
        $b = Candidate::create(['title' => 'B']);

        return [
            'system' => 'list_ranking',
            'voting_type' => 'my_list',
            'owner_type' => 'user',
            'owner_id' => 1,
            'items' => [
                ['type' => Candidate::class, 'id' => $a->id],
                ['type' => Candidate::class, 'id' => $b->id],
            ],
        ];
    }

    public function test_starts_a_session_successfully(): void
    {
        $response = $this->postJson('voting/sessions', $this->validPayload());

        $response->assertCreated()
            ->assertJsonStructure(['session' => ['id', 'system', 'voting_type'], 'next_comparison', 'progress']);

        $this->assertDatabaseHas((new VotingSession)->getTable(), ['system' => 'list_ranking', 'voting_type' => 'my_list']);
    }

    public function test_system_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['system']);

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('system');
    }

    public function test_system_must_be_a_known_value(): void
    {
        $payload = $this->validPayload();
        $payload['system'] = 'not_a_real_system';

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('system');
    }

    public function test_voting_type_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['voting_type']);

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('voting_type');
    }

    public function test_voting_type_max_length(): void
    {
        $payload = $this->validPayload();
        $payload['voting_type'] = str_repeat('x', 51);

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('voting_type');
    }

    public function test_items_are_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['items']);

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_items_require_at_least_two(): void
    {
        $payload = $this->validPayload();
        $payload['items'] = [['type' => Candidate::class, 'id' => 1]];

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items');
    }

    public function test_item_type_and_id_are_required(): void
    {
        $payload = $this->validPayload();
        $payload['items'] = [
            ['id' => 1],
            ['type' => Candidate::class],
        ];

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['items.0.type', 'items.1.id']);
    }

    public function test_item_id_must_be_integer(): void
    {
        $payload = $this->validPayload();
        $payload['items'][0]['id'] = 'not-an-int';

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('items.0.id');
    }

    public function test_win_goal_must_be_positive_integer(): void
    {
        $payload = $this->validPayload();
        $payload['options'] = ['win_goal' => 0];

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('options.win_goal');
    }

    public function test_boolean_options_must_be_boolean(): void
    {
        $payload = $this->validPayload();
        $payload['options'] = ['is_comprehensive' => 'maybe'];

        $this->postJson('voting/sessions', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('options.is_comprehensive');
    }
}
