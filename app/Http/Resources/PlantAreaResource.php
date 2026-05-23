<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlantAreaResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'objectType' => 'plant_area',
            'code' => $this->code,
            'name' => $this->name,
            'areaType' => $this->area_type,
            'description' => $this->description,
            'siteId' => $this->site_id,
            'plantConfigurationId' => $this->plant_configuration_id,
            'status' => $this->status,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
