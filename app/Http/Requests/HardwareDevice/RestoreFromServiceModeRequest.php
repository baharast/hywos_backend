<?php

namespace App\Http\Requests\HardwareDevice;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.4 §10 — restoring a device from service mode is also auditable.
 * Reason is required so the audit trail records WHY a device was put
 * back into automatic routing.
 */
class RestoreFromServiceModeRequest extends FormRequest
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
            'reason.required' => 'A reason is required to restore a device from service mode.',
            'reason.min' => 'Reason must be at least 3 characters.',
        ];
    }
}
