<?php

namespace App\Http\Requests\Driver;

use Illuminate\Foundation\Http\FormRequest;

class CreateTanRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function prepareForValidation(): void
    {
        if (! $this->has('is_single_use')) {
            $this->merge(['is_single_use' => true]);
        }
    }

    public function rules()
    {
        return [
            'driver_id' => 'required|string|size:36|exists:drivers,id',
            'expires_at' => 'required|date|after:now',
            'is_single_use' => 'sometimes|boolean',
            'order_id' => 'nullable|string|size:36',
            'reason_note' => 'nullable|string|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }

    public function messages()
    {
        return [
            'expires_at.after' => 'expires_at must be a future date/time.',
        ];
    }
}
