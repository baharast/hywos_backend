<?php

namespace App\Http\Resources;

use App\Enums\InspectionState;
use App\Enums\TechnicalSuitabilityState;
use App\Enums\TrailerChipState;
use App\Enums\TrailerStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class TrailerResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status ?? TrailerStatus::ACTIVE;
        $chipState = $this->chip_state;
        $inspectionState = $this->inspection_state;
        $suitability = $this->technical_suitability ?? 'incomplete';

        $carrierName = $this->relationLoaded('carrier') ? optional($this->carrier)->name : null;
        $customerName = $this->relationLoaded('customer') ? optional($this->customer)->name : null;

        return [
            'id' => $this->id,
            'trailerCode' => $this->trailer_code,
            'trailerLabel' => $this->trailer_label,
            'plate' => $this->plate,

            'trailerType' => $this->trailer_type,
            'pressureClass' => $this->pressure_class,
            'volume' => $this->volume !== null ? (float) $this->volume : null,
            'volumeUnit' => $this->volume_unit,
            'approvedProductQuality' => $this->approved_product_quality ?? [],

            'inspectionExpiryDate' => $this->inspection_expiry_date?->toDateString(),
            'inspectionReference' => $this->inspection_reference,
            'inspectionState' => [
                'value' => $inspectionState,
                'label' => InspectionState::label($inspectionState),
                'tone' => InspectionState::tone($inspectionState),
            ],

            'technicalSuitability' => [
                'value' => $suitability,
                'label' => TechnicalSuitabilityState::label($suitability),
                'tone' => TechnicalSuitabilityState::tone($suitability),
            ],
            'status' => [
                'value' => $status,
                'label' => TrailerStatus::label($status),
                'tone' => TrailerStatus::tone($status),
            ],
            'chipState' => [
                'value' => $chipState,
                'label' => TrailerChipState::label($chipState),
                'tone' => TrailerChipState::tone($chipState),
            ],
            // Display only — never the raw chip identifier.
            'chipDisplayValue' => $this->chip_display_value,

            'blockReason' => $this->block_reason,
            'blockedAt' => $this->blocked_at?->toIso8601String(),

            'isActive' => (bool) $this->is_active,

            'carrierId' => $this->carrier_id,
            'carrierName' => $carrierName,
            'customerId' => $this->customer_id,
            'customerName' => $customerName,

            'currentParkingId' => $this->current_parking_id,
            'currentContext' => $this->current_context,

            'lastVisitAt' => $this->last_visit_at?->toIso8601String(),

            // Placeholder until clarification_cases module exists.
            'hasClarification' => false,

            'hasNotes' => $this->has_notes,
            'notes' => $this->notes,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
