<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnblockCustomerRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        // TODO: re-enable permission check when auth lands (e.g. customers.block)
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }
}
