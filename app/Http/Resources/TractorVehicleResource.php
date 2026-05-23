<?php

namespace App\Http\Resources;

use App\Enums\CouplingState;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use Illuminate\Http\Resources\Json\JsonResource;

class TractorVehicleResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status ?? VehicleStatus::ACTIVE;
        $type = $this->vehicle_type ?? VehicleType::TRACTOR_UNIT;
        $coupling = $this->coupling_state ?? CouplingState::NOT_COUPLED;

        $carrierName = $this->whenLoaded('carrier')
            ? optional($this->carrier)->name
            : null;
        $defaultDriverName = $this->whenLoaded('defaultDriver') && $this->defaultDriver
            ? trim(($this->defaultDriver->first_name ?? '') . ' ' . ($this->defaultDriver->last_name ?? ''))
            : null;

        return [
            'id' => $this->id,
            'vehicleCode' => $this->vehicle_code,
            'licensePlate' => $this->license_plate,
            'plateCountry' => $this->plate_country,

            'vehicleType' => [
                'value' => $type,
                'label' => VehicleType::label($type),
            ],

            'carrierId' => $this->carrier_id,
            'carrierName' => $carrierName,
            'ownerName' => $this->owner_name,

            'defaultDriverId' => $this->default_driver_id,
            'defaultDriverName' => $defaultDriverName,

            'vin' => $this->vin,
            'registrationExpiry' => $this->registration_expiry?->toDateString(),
            'insuranceExpiry' => $this->insurance_expiry?->toDateString(),

            'status' => [
                'value' => $status,
                'label' => VehicleStatus::label($status),
                'tone' => VehicleStatus::tone($status),
            ],

            'couplingState' => [
                'value' => $coupling,
                'label' => CouplingState::label($coupling),
                'tone' => CouplingState::tone($coupling),
            ],

            'currentTrailerId' => $this->current_trailer_id,
            'currentTrailerLabel' => $this->current_trailer_label,
            'currentVisitId' => $this->current_visit_id,
            'currentVisitNo' => $this->current_visit_no,
            'lastVisitAt' => $this->last_visit_at?->toIso8601String(),
            'lastDriverName' => $this->last_driver_name,
            'hasOpenClarification' => (bool) $this->has_open_clarification,

            'blockReason' => $this->block_reason,
            'blockedAt' => $this->blocked_at?->toIso8601String(),

            'notes' => $this->notes,
            'hasNotes' => ! empty($this->notes),
            'isActive' => (bool) $this->is_active,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
