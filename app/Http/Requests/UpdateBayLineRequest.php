<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBayLineRequest extends FormRequest
{
    /**
     * Authorisation is owned by the route middleware
     * (`permission:plant_configuration.manage` on PUT /api/baylines/{id}).
     * The previous `can('baylines.update')` check referenced an
     * unseeded permission name, so every request returned 403. Single
     * source of truth = the route gate.
     */
    public function authorize(): bool
    {
        return true;
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
        ];
    }
}
