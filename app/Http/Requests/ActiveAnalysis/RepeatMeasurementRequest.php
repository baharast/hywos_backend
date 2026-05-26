<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Repeat Measurement (V1.4 §5 — HA-3). For main-analysis technically
 * invalid when the single technical repeat is still allowed. Reason
 * mandatory.
 *
 * The service enforces "only one technical repeat per main analysis":
 * a second technical-repeat attempt returns 409
 * ANALYSIS_TECHNICAL_REPEAT_USED.
 */
class RepeatMeasurementRequest extends FormRequest
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
            'reason.required' => 'A reason is required to request a measurement repeat.',
        ];
    }
}
