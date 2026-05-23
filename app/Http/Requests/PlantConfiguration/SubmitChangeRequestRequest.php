<?php

namespace App\Http\Requests\PlantConfiguration;

use App\Enums\ChangeRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'affected_object_type' => 'required|string|in:plant_area,gate,terminal_panel,bay_line,parking_area',
            'affected_object_id' => 'required|string|max:36',
            'change_type' => ['required', 'string', Rule::in(ChangeRequestType::all())],
            'proposed_values' => 'required|array',
            'reason' => 'required|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
