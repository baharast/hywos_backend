<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reject Loading / Block Trailer (V1.4 §5 — VA-4). Only valid for a
 * 3rd functionally-NOK pre-analysis. Reason is mandatory.
 */
class RejectLoadingRequest extends FormRequest
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
            'reason.required' => 'A reason is required to reject loading / block the trailer.',
        ];
    }
}
