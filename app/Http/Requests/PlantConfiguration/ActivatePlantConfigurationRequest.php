<?php

namespace App\Http\Requests\PlantConfiguration;

use Illuminate\Foundation\Http\FormRequest;

class ActivatePlantConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'confirmed' => 'required|boolean|accepted',
            'reason' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Activation requires explicit confirmation. Tick the confirmation field.',
        ];
    }
}
