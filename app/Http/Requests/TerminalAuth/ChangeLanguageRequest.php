<?php

namespace App\Http\Requests\TerminalAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Change the terminal session language (V3 §10 + §22 fallback rule).
 *
 * `culture_code` is an ISO/BCP-47 short code (e.g. `de`, `en`, `pl`).
 * Backend validates the code is in the terminal's `language_support` list
 * (§21.1) before writing it onto the session; falls back to `de` (German)
 * silently if the requested code is not configured.
 */
class ChangeLanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'culture_code' => 'required|string|min:2|max:10',
        ];
    }
}
