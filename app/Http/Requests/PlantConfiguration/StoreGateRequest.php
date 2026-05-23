<?php

namespace App\Http\Requests\PlantConfiguration;

use App\Enums\GateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGateRequest extends FormRequest
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
            'gate_type' => ['required', 'string', Rule::in(GateType::all())],
            'related_terminal_id' => 'nullable|string|max:36',
            'related_device_id' => 'nullable|string|max:36',
            'notes' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
