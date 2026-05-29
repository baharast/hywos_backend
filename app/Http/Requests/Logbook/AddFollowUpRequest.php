<?php

namespace App\Http\Requests\Logbook;

use Illuminate\Foundation\Http\FormRequest;

class AddFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'follow_up_owner_user_id' => 'nullable|integer',
            'follow_up_owner_role' => 'nullable|string|max:50',
            'follow_up_due_at' => 'required|date',
            'reason' => 'nullable|string',
        ];
    }
}
