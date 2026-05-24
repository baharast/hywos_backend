<?php

namespace App\Http\Requests\ChipCard;

use Illuminate\Foundation\Http\FormRequest;

class MarkLostChipCardRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
            'assignment_action' => 'nullable|string|in:keep,unassign',
        ];
    }
}
