<?php

namespace App\Http\Requests\PlantConfiguration;

use App\Enums\TerminalType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTerminalPanelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => 'required|string|max:36',
            'plant_area_id' => 'nullable|string|max:36',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'terminal_type' => ['required', 'string', Rule::in(TerminalType::all())],
            'related_device_id' => 'nullable|string|max:36',
            'language_support' => 'nullable|array',
            'language_support.*' => 'string|max:10',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
