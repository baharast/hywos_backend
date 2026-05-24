<?php

namespace App\Http\Requests\Tan;

use App\Services\Tans\TanPolicy;
use Illuminate\Foundation\Http\FormRequest;

class GenerateTanRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        $maxExpiry = now()->addDays(TanPolicy::MAX_VALIDITY_DAYS)->toIso8601String();

        return [
            'driver_id' => 'required|string|size:36|exists:drivers,id',
            'valid_from' => 'nullable|date',
            'expires_at' => "required|date|after:valid_from|before_or_equal:{$maxExpiry}",
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
            'related_plant_visit_id' => 'nullable|string|size:36',
            'related_order_id' => 'nullable|string|size:36',
            'related_terminal_session_id' => 'nullable|string|size:36',
        ];
    }

    public function messages()
    {
        return [
            'expires_at.before_or_equal' => 'Expiry must be within ' . TanPolicy::MAX_VALIDITY_DAYS . ' days from now.',
            'expires_at.after' => 'Expiry must be after valid_from.',
        ];
    }
}
