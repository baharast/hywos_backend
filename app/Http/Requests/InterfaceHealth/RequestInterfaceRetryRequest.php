<?php

namespace App\Http\Requests\InterfaceHealth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.4 §9 — manual retry needs a `reason` so the audit row can explain
 * WHY support hit retry (helps trace problem-pattern across interfaces).
 * The Sanctum + system_devices.manage check is on the route, not here.
 */
class RequestInterfaceRetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:500',
        ];
    }
}
