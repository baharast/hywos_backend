<?php

namespace App\Http\Requests\OperationalDocument;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Invalidate / cancel an operational document (V1.2 §13 — Invalidate /
 * Cancel Document, §18 No hard delete rule).
 *
 * `reason` is required — invalidation/cancellation is auditable and the
 * generated file is preserved (only the lifecycle status moves to
 * invalidated/cancelled). `as_cancelled` switches between the two terminal
 * states: invalidated means a generated file is being annulled,
 * cancelled is the abort before generation/handover.
 */
class InvalidateDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:5000',
            'as_cancelled' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to invalidate or cancel a document.',
        ];
    }
}
