<?php

namespace App\Http\Requests\TerminalAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Manager / Operator logon from the terminal popup (V3 §11).
 *
 * Validates a dashboard `users` row by username + password. On success the
 * controller issues a Sanctum personal access token bound to the user.
 * Driver TAN/chip transient state is cleared on the FE — backend never
 * holds joint sessions.
 */
class ManagerLogonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => 'required|string|max:100',
            'password' => 'required|string|max:255',
            'terminal_id' => 'nullable|string|max:36',
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ];
    }
}
