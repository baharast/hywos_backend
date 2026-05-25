<?php

namespace App\Http\Requests\ClarificationCase;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Close a clarification case (V1.3 §4.1 `resolved → closed`, sticky). No
 * reason required by the spec — `resolved` already captured the
 * explanation. Optional `notes` accepted as audit context.
 */
class CloseClarificationCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
