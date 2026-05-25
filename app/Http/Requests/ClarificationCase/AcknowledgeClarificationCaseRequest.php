<?php

namespace App\Http\Requests\ClarificationCase;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Acknowledge a clarification case (V1.3 §4.1 `open → in_progress`). The
 * state-machine check itself lives in the controller; this request just
 * validates the (empty) body so we get a uniform 422 envelope for any
 * unexpected payload.
 */
class AcknowledgeClarificationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Auth disabled in dev — see 00-conventions.md §2.
        return true;
    }

    public function rules(): array
    {
        return [
            // Optional free-text — accepted but ignored if present so the FE
            // can pass a default `{}` body without 422'ing.
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
