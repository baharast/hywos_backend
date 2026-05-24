<?php

namespace App\Http\Requests\ChipCard;

use App\Enums\ChipCardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateChipCardRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'card_code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('auth_media', 'card_code')->ignore($id),
            ],
            'card_type' => ['sometimes', 'required', 'string', Rule::in(ChipCardType::all())],
            'serial_number' => 'nullable|string|max:100',
            'masked_uid' => 'nullable|string|max:64',
            'expires_at' => 'nullable|date',
            'notes' => 'nullable|string',

            // Lifecycle-controlled fields are explicitly forbidden here:
            'status' => 'prohibited',
            'assignment_state' => 'prohibited',
            'lost_at' => 'prohibited',
            'defective_at' => 'prohibited',
            'archived_at' => 'prohibited',
            'last_used_at' => 'prohibited',
            'last_used_context' => 'prohibited',
            'last_used_source' => 'prohibited',
            'last_usage_result' => 'prohibited',
        ];
    }
}
