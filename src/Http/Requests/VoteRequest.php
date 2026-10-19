<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a cast-vote request: a winner and a loser item, plus an optional
 * record of which side was shown first (for undo).
 */
class VoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'winner' => ['required', 'array'],
            'winner.type' => ['required', 'string', 'max:255'],
            'winner.id' => ['required', 'integer'],
            'winner.list_id' => ['nullable', 'integer'],

            'loser' => ['required', 'array'],
            'loser.type' => ['required', 'string', 'max:255'],
            'loser.id' => ['required', 'integer'],
            'loser.list_id' => ['nullable', 'integer'],

            'first_shown' => ['sometimes', 'array'],
            'first_shown.type' => ['required_with:first_shown', 'string', 'max:255'],
            'first_shown.id' => ['required_with:first_shown', 'integer'],
        ];
    }
}
