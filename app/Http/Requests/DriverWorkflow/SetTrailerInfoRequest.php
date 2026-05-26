<?php

namespace App\Http\Requests\DriverWorkflow;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V6 §8.2 — Chip-card path. The driver picks one of three trailer
 * situations; a plate is required only when the chip is attached but
 * unscannable.
 */
class SetTrailerInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // identity is the session id in the URL.
    }

    public function rules(): array
    {
        return [
            'situation' => ['required', 'string', Rule::in(['chip_present', 'chip_attached', 'none'])],
            'trailerPlate' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
                Rule::requiredIf(fn () => $this->input('situation') === 'chip_attached'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'situation.in' => 'situation must be one of chip_present, chip_attached, none.',
            'trailerPlate.required' => 'trailerPlate is required when situation is chip_attached.',
        ];
    }
}
