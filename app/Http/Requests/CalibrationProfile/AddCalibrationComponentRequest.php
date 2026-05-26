<?php

namespace App\Http\Requests\CalibrationProfile;

use App\Enums\GasComponent;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add one calibration component row (V2.1 §5.3 + §5.6).
 *
 * `exact_value` is REQUIRED — that's the reference concentration from
 * the cal-gas certificate. Tolerance is required as well; the FE may
 * use absolute (`tolerance_abs`) or percent (`tolerance_percent`) — at
 * least one must be present.
 *
 * Read-only fields (last_measured_value, last_deviation, last_result)
 * are stripped silently if submitted (V2.1 §5.7).
 */
class AddCalibrationComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'component' => 'required|in:' . implode(',', GasComponent::all()),
            'unit' => 'required|string|max:20',
            'exact_value' => 'required|numeric',
            'tolerance_abs' => 'nullable|numeric|min:0',
            'tolerance_percent' => 'nullable|numeric|min:0|max:100',
            'precision_decimals' => 'nullable|integer|min:0|max:9',
            'rounding_rule' => 'nullable|in:round,truncate,banker',
            'reason' => 'nullable|string|max:5000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (is_null($this->input('tolerance_abs')) && is_null($this->input('tolerance_percent'))) {
                $v->errors()->add(
                    'tolerance_abs',
                    'At least one of tolerance_abs or tolerance_percent is required (V2.1 §5.3).'
                );
            }
        });
    }
}
