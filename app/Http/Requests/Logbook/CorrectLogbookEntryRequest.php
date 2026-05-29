<?php

namespace App\Http\Requests\Logbook;

use Illuminate\Foundation\Http\FormRequest;

class CorrectLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'action_taken' => 'nullable|string',
            'reason' => 'required|string|min:3',
            'correlation_id' => 'nullable|string|max:64',
        ];
    }
}
