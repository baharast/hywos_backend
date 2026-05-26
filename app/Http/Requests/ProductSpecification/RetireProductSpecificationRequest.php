<?php

namespace App\Http\Requests\ProductSpecification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Retire an active Product Specification (V2.1 §8 — "Allowed only if
 * backend confirms safe to retire. Requires reason.").
 */
class RetireProductSpecificationRequest extends FormRequest
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
            'reason.required' => 'A reason is required to retire a specification.',
            'reason.min' => 'Retire reason must be at least 3 characters.',
        ];
    }
}
