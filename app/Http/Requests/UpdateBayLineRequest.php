<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBayLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('baylines.update');
    }

    public function rules(): array
    {
        $baylineId = $this->route('id');

        return [
            'code' => "required|string|max:100|unique:baylines,code,{$baylineId}",
            'name' => 'nullable|string|max:255',
            'site_id' => 'nullable|uuid',
            'plant_area_id' => 'nullable|uuid',
            'status_code' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ];
    }
}
