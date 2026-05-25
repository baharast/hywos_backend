<?php

namespace App\Http\Requests\PlantVisit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.6 §11.5 — Force-close is the admin escape hatch and is allowed from
 * ANY status. Reason is REQUIRED so the audit trail can distinguish it
 * from a clean close.
 */
class ForceCloseVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
