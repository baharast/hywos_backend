<?php

namespace App\Http\Requests\ClarificationCase;

use App\Enums\ClarificationEntityType;
use App\Enums\ClarificationSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateClarificationCaseRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'category' => 'required|string|max:40',
            'description' => 'required|string|max:5000',
            'entity_type' => ['required', 'string', Rule::in(ClarificationEntityType::all())],
            'entity_id' => 'required|string|max:36',
            'entity_label' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'reason_code' => 'nullable|string|max:100',
            'severity' => ['nullable', 'string', Rule::in(ClarificationSeverity::all())],
            'owner_role' => 'nullable|string|max:50',
            'is_blocking' => 'nullable|boolean',
            'related_plant_visit_id' => 'nullable|string|size:36',
            'related_order_id' => 'nullable|string|size:36',
            'related_driver_id' => 'nullable|string|size:36',
            'related_trailer_id' => 'nullable|string|size:36',
            'correlation_id' => 'nullable|string|max:64',
        ];
    }
}
