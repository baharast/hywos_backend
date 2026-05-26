<?php

namespace App\Http\Requests\TerminalAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Driver login with a TAN (V3 §9.1).
 *
 * `terminal_id` identifies the physical terminal so the backend can:
 *   1. Verify the terminal is online and not in service mode (§14)
 *   2. Stamp the TAN consumption with terminal context (§21.1 audit rule)
 *   3. Pin the resulting terminal_session to this device
 *
 * `language` is optional — when omitted the resolved driver's
 * preferred_culture_code (or German fallback) is used per §22 rule.
 */
class AuthenticateTanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terminal_id' => 'required|string|max:36',
            'tan' => 'required|string|min:4|max:64',
            'language' => 'nullable|string|max:10',
        ];
    }

    public function messages(): array
    {
        return [
            'tan.required' => 'TAN Number is required.',
        ];
    }
}
