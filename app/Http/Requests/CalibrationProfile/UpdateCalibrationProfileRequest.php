<?php

namespace App\Http\Requests\CalibrationProfile;

use App\Enums\CalibrationLockoutBehavior;
use App\Enums\CalibrationProfileStatus;
use App\Models\CalibrationProfile;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCalibrationProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'device_name' => 'sometimes|nullable|string|max:255',
            'calibration_medium' => 'sometimes|string|max:100',
            'certificate_ref' => 'sometimes|nullable|string|max:100',
            'lockout_behavior' => 'sometimes|in:' . implode(',', CalibrationLockoutBehavior::all()),
            'medium_expiry_at' => 'sometimes|nullable|date',
            'next_due_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:5000',
            'reason' => $this->reasonRule(),
        ];
    }

    protected function reasonRule(): string
    {
        $profile = $this->route('id')
            ? CalibrationProfile::find($this->route('id'))
            : null;

        if ($profile && CalibrationProfileStatus::requiresReasonForEdit($profile->status)) {
            return 'required|string|min:3|max:5000';
        }
        return 'nullable|string|max:5000';
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A change reason is required when editing an active calibration profile.',
        ];
    }
}
