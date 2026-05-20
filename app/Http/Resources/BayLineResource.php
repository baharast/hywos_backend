<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BayLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'site_id' => $this->site_id,
            'plant_area_id' => $this->plant_area_id,
            'status_code' => $this->status_code,
            'current_trailer_id' => $this->current_trailer_id,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
