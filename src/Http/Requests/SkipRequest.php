<?php

declare(strict_types=1);

namespace Polis\Plugin\Voting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a skip-comparison request: the two items of the presented pair.
 */
class SkipRequest extends FormRequest
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
            'item_a' => ['required', 'array'],
            'item_a.type' => ['required', 'string', 'max:255'],
            'item_a.id' => ['required', 'integer'],
            'item_a.list_id' => ['nullable', 'integer'],

            'item_b' => ['required', 'array'],
            'item_b.type' => ['required', 'string', 'max:255'],
            'item_b.id' => ['required', 'integer'],
            'item_b.list_id' => ['nullable', 'integer'],
        ];
    }
}
