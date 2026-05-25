<?php

namespace App\Http\Requests\PlantVisit;

use Illuminate\Foundation\Http\FormRequest;

class MarkReadyForExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // No body required per V1.6 §11.4.
        return [
            'reason' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
