<?php

namespace App\Http\Requests\PlantVisit;

use Illuminate\Foundation\Http\FormRequest;

class CloseVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Clean close needs no fields per V1.6 §11.5. We accept optional
        // reason / reason_code for audit consistency with other lifecycle
        // endpoints, but they're not required.
        return [
            'reason' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
