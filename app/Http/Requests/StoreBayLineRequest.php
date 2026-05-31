<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBayLineRequest extends FormRequest
{
    /**
     * Authorisation is owned by the route middleware
     * (`permission:plant_configuration.manage` on POST /api/baylines).
     * The previous `can('baylines.create')` check referenced a
     * permission that was never seeded into Spatie's registry, so
     * every request — including admin's — returned 403
     * "This action is unauthorized.". Single source of truth = the
     * route gate; do not re-check here.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:100|unique:baylines,code',
            'name' => 'nullable|string|max:255',
            'site_id' => 'nullable|uuid',
            'plant_area_id' => 'nullable|uuid',
            'status_code' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
        ];
    }
}
