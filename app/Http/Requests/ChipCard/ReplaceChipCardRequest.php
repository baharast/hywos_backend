<?php

namespace App\Http\Requests\ChipCard;

use Illuminate\Foundation\Http\FormRequest;

class ReplaceChipCardRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'replacement_card_id' => 'required|string|size:36|exists:auth_media,id',
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
            'carry_assignment' => 'nullable|boolean',
            'old_card_final_status' => 'nullable|string|in:replaced,blocked,archived',
        ];
    }
}
