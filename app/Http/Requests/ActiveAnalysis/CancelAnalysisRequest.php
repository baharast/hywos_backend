<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

class CancelAnalysisRequest extends FormRequest
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
            'reason.required' => 'A reason is required to cancel an analysis.',
        ];
    }
}
