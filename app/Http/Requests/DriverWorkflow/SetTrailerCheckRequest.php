<?php

namespace App\Http\Requests\DriverWorkflow;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V6 §8.3 — TAN path. The driver answers yes/no on trailer presence; if
 * yes, a plate is required.
 */
class SetTrailerCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hasTrailer' => ['required', 'boolean'],
            'trailerPlate' => [
                'nullable',
                'string',
                'min:2',
                'max:50',
                'required_if:hasTrailer,true,1',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'trailerPlate.required_if' => 'trailerPlate is required when hasTrailer is true.',
        ];
    }
}
