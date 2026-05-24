<?php

namespace App\Http\Requests\ChipCard;

use App\Enums\ChipCardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreChipCardRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'card_code' => 'required|string|max:50|unique:auth_media,card_code',
            'card_type' => ['required', 'string', Rule::in(ChipCardType::all())],
            'serial_number' => 'nullable|string|max:100',
            'masked_uid' => 'nullable|string|max:64',
            'expires_at' => 'nullable|date|after:now',
            'notes' => 'nullable|string',

            // Optional combined register+assign payload.
            'assignment' => 'nullable|array',
            'assignment.entity_type' => 'required_with:assignment|string|in:driver,trailer',
            'assignment.entity_id' => 'required_with:assignment|string|size:36',
            'assignment.reason' => 'nullable|string|max:1000',
            'assignment.reason_code' => 'nullable|string|max:100',
        ];
    }
}
