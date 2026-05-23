<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ParkingResource extends JsonResource
{
    public function toArray($request): array
    {
        $statusCode = $this->status_code ?: 'free';
        $tone = match ($statusCode) {
            'free' => 'success',
            'reserved' => 'info',
            'occupied' => 'warning',
            'blocked' => 'danger',
            'maintenance' => 'maintenance',
            'offline' => 'offline',
            default => 'neutral',
        };

        $capacity = (int) $this->capacity;
        $occupied = (int) $this->occupied_count;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'siteId' => $this->site_id,
            'areaId' => $this->area_id,
            'plantConfigurationId' => $this->plant_configuration_id,
            'spaceType' => $this->space_type,
            'readerHardwareId' => $this->reader_hardware_id,
            'capacity' => $capacity,
            'occupiedCount' => $occupied,
            'available' => max(0, $capacity - $occupied),
            'currentVehicleId' => $this->current_vehicle_id,
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
