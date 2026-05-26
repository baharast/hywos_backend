<?php

namespace App\Http\Requests\CalibrationProfile;

use Illuminate\Foundation\Http\FormRequest;

class RetireCalibrationProfileRequest extends FormRequest
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
            'reason.required' => 'A reason is required to retire a calibration profile.',
        ];
    }
}
