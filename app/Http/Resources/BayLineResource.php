<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BayLineResource extends JsonResource
{
    public function toArray($request): array
    {
        $statusCode = $this->status_code ?: 'free';
        $tone = match ($statusCode) {
            'free' => 'success',
            'reserved' => 'info',
            'occupied' => 'warning',
            'loading' => 'info',
            'fault' => 'danger',
            'maintenance' => 'maintenance',
            'offline' => 'offline',
            default => 'neutral',
        };

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'siteId' => $this->site_id,
            'plantAreaId' => $this->plant_area_id,
            'plantConfigurationId' => $this->plant_configuration_id,
            'pressureClass' => $this->pressure_class,
            'allowedProduct' => $this->allowed_product,
            'relatedPanelId' => $this->related_panel_id,
            'relatedDeviceId' => $this->related_device_id,
            'currentTrailerId' => $this->current_trailer_id,
            'status' => [
                'value' => $statusCode,
                'label' => ucfirst(str_replace('_', ' ', $statusCode)),
                'tone' => $tone,
            ],
            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
