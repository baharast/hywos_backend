<?php

namespace App\Http\Requests\Alarm;

use App\Enums\AlarmOwnerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignAlarmRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'owner_role' => ['required', Rule::in(AlarmOwnerRole::all())],
            'owner_user_id' => 'nullable|integer',
            'owner_user_name' => 'nullable|string|max:255',
            'reason' => 'nullable|string',
        ];
    }
}
