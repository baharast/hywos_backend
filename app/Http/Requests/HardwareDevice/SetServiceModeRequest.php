<?php

namespace App\Http\Requests\HardwareDevice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.4 §10 — set service mode requires reason + audit + event. Optional
 * `expected_end_at` so support can see when the device is expected back.
 */
class SetServiceModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:5000',
            'expected_end_at' => 'sometimes|nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reason is required to set service mode.',
            'reason.min' => 'Reason must be at least 3 characters.',
            'expected_end_at.after' => 'Expected end time must be in the future.',
        ];
    }
}
