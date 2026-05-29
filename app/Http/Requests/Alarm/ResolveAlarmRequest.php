<?php

namespace App\Http\Requests\Alarm;

use Illuminate\Foundation\Http\FormRequest;

class ResolveAlarmRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'resolution_reason' => 'nullable|string',
            'corrective_action' => 'nullable|string',
        ];
    }
}
