<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TerminalPanelResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'objectType' => 'terminal_panel',
            'code' => $this->code,
            'name' => $this->name,
            'terminalType' => $this->terminal_type,
            'plantAreaId' => $this->plant_area_id,
            'siteId' => $this->site_id,
            'plantConfigurationId' => $this->plant_configuration_id,
            'relatedDeviceId' => $this->related_device_id,
            'languageSupport' => $this->language_support ?? [],
            'status' => $this->status,
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
