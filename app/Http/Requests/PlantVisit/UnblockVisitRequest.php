<?php

namespace App\Http\Requests\PlantVisit;

use Illuminate\Foundation\Http\FormRequest;

class UnblockVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
