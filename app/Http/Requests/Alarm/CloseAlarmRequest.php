<?php

namespace App\Http\Requests\Alarm;

use Illuminate\Foundation\Http\FormRequest;

class CloseAlarmRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string',
            'resolution_reason' => 'nullable|string',
            'corrective_action' => 'nullable|string',
        ];
    }
}
