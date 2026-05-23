<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EnableUserRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        // TODO: re-enable permission check when auth lands (e.g. users.update)
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
