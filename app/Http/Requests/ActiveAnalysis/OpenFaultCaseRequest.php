<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Open Fault Case / Manual Check Required (V1.4 §5 — VA-5 + HA-4).
 * Reason is mandatory + the affected device/sample/element must be
 * visible.
 *
 * `affected_device_bmk` and `affected_element` are optional context
 * that the FE can pass for richer audit metadata; the service will
 * fall back to the analysis row's snapshot if either is missing.
 */
class OpenFaultCaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:5000',
            'affected_device_bmk' => 'nullable|string|max:50',
            'affected_element' => 'nullable|in:H2,O2,N2,CH4,CO,CO2',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to open a fault case / manual check.',
        ];
    }
}
