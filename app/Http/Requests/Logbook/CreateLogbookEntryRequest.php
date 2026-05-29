<?php

namespace App\Http\Requests\Logbook;

use App\Enums\LogbookArea;
use App\Enums\LogbookCategory;
use App\Enums\LogbookSeverity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateLogbookEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_label' => 'nullable|string|max:50',
            'category' => ['required', Rule::in(LogbookCategory::all())],
            'severity' => ['required', Rule::in(LogbookSeverity::all())],
            'area' => ['nullable', Rule::in(LogbookArea::all())],
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'action_taken' => 'nullable|string',
            'related_entity_type' => 'nullable|string|max:100',
            'related_entity_id' => 'nullable|string|max:100',
            'linked_alarm_id' => 'nullable|string|max:36',
            'linked_event_log_id' => 'nullable|integer',
            'linked_clarification_case_id' => 'nullable|string|max:36',
            'follow_up_required' => 'nullable|boolean',
            'follow_up_owner_user_id' => 'nullable|integer',
            'follow_up_owner_role' => 'nullable|string|max:50',
            'follow_up_due_at' => 'nullable|date',
            'handover_flag' => 'nullable|boolean',
            'correlation_id' => 'nullable|string|max:64',
        ];
    }
}
