<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Put an analysis on hold (V1.4 §5 — "Put On Hold"). Reason is
 * mandatory per spec.
 */
class PutOnHoldRequest extends FormRequest
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
            'reason.required' => 'A reason is required to put an analysis on hold.',
            'reason.min' => 'Hold reason must be at least 3 characters.',
        ];
    }
}
