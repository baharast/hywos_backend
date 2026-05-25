<?php

namespace App\Http\Requests\ClarificationCase;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Resolve a clarification case (V1.3 §4.1 `open | in_progress |
 * waiting_for_owner → resolved`).
 *
 * `resolution_note` is required — this is the auditable explanation of how
 * the blocker was handled. Stored on the row + emitted in the
 * `CLARIFICATION_RESOLVED` audit row.
 */
class ResolveClarificationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'resolution_note' => 'required|string|min:3|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'resolution_note.required' => 'A resolution note is required to resolve a clarification case.',
            'resolution_note.min' => 'Resolution note must be at least 3 characters.',
        ];
    }
}
