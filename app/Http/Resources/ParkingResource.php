<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParkingResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'site_id' => $this->site_id,
            'area_id' => $this->area_id,
            'capacity' => (int) $this->capacity,
            'occupied_count' => (int) $this->occupied_count,
            'available' => max(0, ((int) $this->capacity - (int) $this->occupied_count)),
            'status_code' => $this->status_code,
            'current_vehicle_id' => $this->current_vehicle_id,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
