<?php

namespace App\Http\Requests\DriverWorkflow;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V6 §8.4 — Tractor / Machine plate. Plate is mandatory before Task
 * Selection (V6 §8.12 acceptance criteria).
 */
class SetTractorPlateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'min:2', 'max:50'],
        ];
    }
}
