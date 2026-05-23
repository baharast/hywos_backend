<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TractorTrailerCouplingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tractorVehicleId' => $this->tractor_vehicle_id,
            'trailerId' => $this->trailer_id,
            'trailerLabel' => $this->trailer_label,
            'driverId' => $this->driver_id,
            'driverName' => $this->driver_name,
            'plantVisitId' => $this->plant_visit_id,
            'visitNo' => $this->visit_no,
            'orderId' => $this->order_id,
            'orderNo' => $this->order_no,
            'source' => $this->source,
            'coupledAt' => $this->coupled_at?->toIso8601String(),
            'uncoupledAt' => $this->uncoupled_at?->toIso8601String(),
            'isActive' => (bool) $this->is_active,
            'correlationId' => $this->correlation_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
