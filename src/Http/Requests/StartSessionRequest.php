<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Polis\Plugin\Voting\Enums\VotingSystem;

/**
 * Validates a start-session request. `system` and `voting_type` are REQUIRED —
 * never defaulted — per the voting plugin's design: nothing may silently pick a
 * system or category for the caller.
 */
class StartSessionRequest extends FormRequest
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
            'system' => ['required', 'string', Rule::in(VotingSystem::values())],
            'voting_type' => ['required', 'string', 'max:50'],

            'items' => ['required', 'array', 'min:2'],
            'items.*.type' => ['required', 'string', 'max:255'],
            'items.*.id' => ['required', 'integer'],
            'items.*.list_id' => ['nullable', 'integer'],

            'options' => ['sometimes', 'array'],
            'options.subset_only' => ['sometimes', 'boolean'],
            'options.is_comprehensive' => ['sometimes', 'boolean'],
            'options.is_revote' => ['sometimes', 'boolean'],
            'options.is_find_the_best' => ['sometimes', 'boolean'],
            'options.win_goal' => ['sometimes', 'integer', 'min:1'],
            'options.list_id' => ['sometimes', 'nullable', 'integer'],
            'options.focus' => ['sometimes', 'array'],
            'options.focus.type' => ['required_with:options.focus', 'string', 'max:255'],
            'options.focus.id' => ['required_with:options.focus', 'integer'],
            'options.focus.list_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
