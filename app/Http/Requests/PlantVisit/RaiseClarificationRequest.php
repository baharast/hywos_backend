<?php

namespace App\Http\Requests\PlantVisit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.6 §11.3 — operator raises a clarification on the visit. When
 * `create_case=true`, the controller ALSO creates a `clarification_cases`
 * row and back-fills the visit's `clarification_case_id`.
 */
class RaiseClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
            'category' => 'required|string|max:100',
            'create_case' => 'sometimes|boolean',
        ];
    }
}
