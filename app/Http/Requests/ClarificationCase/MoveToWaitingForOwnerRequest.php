<?php

namespace App\Http\Requests\ClarificationCase;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Move a clarification case to `waiting_for_owner` (V1.3 §4.1). Used when
 * the case depends on another role/module (IT, Analysis, Documents,
 * Dispatcher) and the current handler is parking it.
 *
 * `reason` is required — this is the auditable moment that explains WHY
 * the case is parked. The controller appends it to the row's `notes` with
 * a timestamp prefix.
 */
class MoveToWaitingForOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
        ];
    }
}
