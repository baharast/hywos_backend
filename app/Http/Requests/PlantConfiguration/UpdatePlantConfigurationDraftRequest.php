<?php

namespace App\Http\Requests\PlantConfiguration;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlantConfigurationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => 'sometimes|string|max:255',
            'company_code' => 'sometimes|string|max:50',
            'site_name' => 'sometimes|string|max:255',
            'site_code' => 'sometimes|string|max:50',
            'plant_type' => 'sometimes|string|max:50',
            'default_language' => 'sometimes|string|max:10',
            'time_zone' => 'sometimes|string|max:50',
        ];
    }
}
