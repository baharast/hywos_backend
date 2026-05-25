<?php

namespace App\Http\Requests\PlantVisit;

use Illuminate\Foundation\Http\FormRequest;

class ResumeVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Reason optional on resume (mirrors enable/unblock conventions).
            'reason' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
