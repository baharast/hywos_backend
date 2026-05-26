<?php

namespace App\Http\Requests\TerminalAuth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Driver login with a chip card (V3 §9.2).
 *
 * The frontend sends either `card_code` (the printed/laser-engraved code
 * matching `auth_media.card_code`) or `masked_uid` (the hashed UID surface
 * the reader produces). Backend prefers `card_code`; falls back to
 * `masked_uid` only when the card_code is absent. Raw UID values are never
 * accepted — see V3 §15.2 ("safe identifier/reference; raw identifier
 * handling follows backend/security policy").
 *
 * `is_simulated` lets the FE flag a Tab-key demo scan per V3 §9.3 — the
 * backend logs the attempt with `source='demo'` so production audits stay
 * distinct from demo terminals.
 */
class AuthenticateChipCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'terminal_id' => 'required|string|max:36',
            'card_code' => 'nullable|string|max:50',
            'masked_uid' => 'nullable|string|max:64',
            'language' => 'nullable|string|max:10',
            'is_simulated' => 'sometimes|boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            if (empty($this->card_code) && empty($this->masked_uid)) {
                $v->errors()->add('card_code', 'Either card_code or masked_uid is required.');
            }
        });
    }
}
