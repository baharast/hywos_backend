<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateParkingRequest extends FormRequest
{
    public function authorize()
    {
        return $this->user() ? $this->user()->can('parkings.update') : true;
    }

    public function rules()
    {
        $id = $this->route('id');

        return [
            'code' => 'required|string|max:50|unique:parkings,code,' . $id . ',id',
            'name' => 'required|string|max:255',
            'site_id' => 'nullable|string|max:36',
            'area_id' => 'nullable|string|max:36',
            'capacity' => 'nullable|integer|min:0',
            'occupied_count' => 'nullable|integer|min:0',
            'status_code' => 'nullable|string|max:50',
            'current_vehicle_id' => 'nullable|string|max:36',
        ];
    }
}
