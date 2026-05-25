<?php

namespace App\Http\Requests\Parking;

use Illuminate\Foundation\Http\FormRequest;

class UnblockSlotRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'nullable|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
