<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\ApiResponse;

class ResetUserAccessRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        // TODO: re-enable permission check when auth lands (e.g. users.reset_access)
        return true;
    }

    public function rules()
    {
        return [
            'reason' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
            // password is explicitly disallowed: the action revokes access, it does not set credentials.
            'password' => 'prohibited',
            'password_confirmation' => 'prohibited',
        ];
    }

    public function messages()
    {
        return [
            'password.prohibited' => 'Password may not be supplied to the reset-access endpoint.',
            'password_confirmation.prohibited' => 'Password may not be supplied to the reset-access endpoint.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::validation($validator->errors()->toArray())
        );
    }
}
