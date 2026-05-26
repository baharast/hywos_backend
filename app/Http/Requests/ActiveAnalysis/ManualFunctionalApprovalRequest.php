<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manual Functional Approval (V1.4 §5 — HA-5).
 *
 * "Exceptional" action: approves a functionally-NOK main analysis as
 * a documented manual override. Reason + approval metadata are
 * mandatory; future role check will gate this to Analysis Specialist /
 * full-access permission.
 */
class ManualFunctionalApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:10|max:5000',
            // The FE may pass a structured justification block for the audit row.
            'justification_category' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A detailed justification is required for manual functional approval.',
            'reason.min' => 'Manual approval justification must be at least 10 characters.',
        ];
    }
}
