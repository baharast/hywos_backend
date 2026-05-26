<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request a repeat / retest (V1.4 §5 — "Request Repeat Analysis").
 *
 * Reason is mandatory. The service validates `attempt_count <
 * max_attempts` before creating a new attempt; over-budget retries
 * return 409 ANALYSIS_MAX_ATTEMPTS_REACHED so the FE can route the
 * operator to the open-fault-case action instead.
 */
class RequestRepeatAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to request a repeat / retest.',
        ];
    }
}
