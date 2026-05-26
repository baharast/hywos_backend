<?php

namespace App\Http\Requests\CalibrationProfile;

use App\Enums\CalibrationProfileStatus;
use App\Models\CalibrationProfile;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit one calibration component row (V2.1 §5.6 + §7).
 *
 * `component` is INTENTIONALLY immutable; changing the component on an
 * existing row is forbidden by the unique key + V2.1 row-edit rules.
 * Read-only fields (last_*) are silently stripped by the service.
 */
class UpdateCalibrationComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit' => 'sometimes|string|max:20',
            'exact_value' => 'sometimes|numeric',
            'tolerance_abs' => 'sometimes|nullable|numeric|min:0',
            'tolerance_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'precision_decimals' => 'sometimes|nullable|integer|min:0|max:9',
            'rounding_rule' => 'sometimes|nullable|in:round,truncate,banker',
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
            'reason.required' => 'A change reason is required when editing a row on an active calibration profile.',
        ];
    }
}
