<?php

namespace App\Http\Requests\PlantConfiguration;

use App\Enums\PlantAreaType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlantAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => 'required|string|max:36',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'area_type' => ['nullable', 'string', Rule::in(PlantAreaType::all())],
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
