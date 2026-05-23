<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class GateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'objectType' => 'gate',
            'code' => $this->code,
            'name' => $this->name,
            'gateType' => $this->gate_type,
            'plantAreaId' => $this->plant_area_id,
            'siteId' => $this->site_id,
            'plantConfigurationId' => $this->plant_configuration_id,
            'relatedTerminalId' => $this->related_terminal_id,
            'relatedDeviceId' => $this->related_device_id,
            'notes' => $this->notes,
            'status' => $this->status,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
