<?php

namespace App\Http\Requests\PlantConfiguration;

use Illuminate\Foundation\Http\FormRequest;

class StartPlantConfigurationDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => 'nullable|string|max:36',
            'company_id' => 'nullable|string|max:36',
            'company_name' => 'required|string|max:255',
            'company_code' => 'required|string|max:50',
            'site_name' => 'required|string|max:255',
            'site_code' => 'required|string|max:50',
            'plant_type' => 'required|string|max:50',
            'default_language' => 'nullable|string|max:10',
            'time_zone' => 'nullable|string|max:50',
        ];
    }
}
