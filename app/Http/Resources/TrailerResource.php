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

            // -----------------------------------------------------------------
            // V2 additions for the redesigned list page (2026-05-31).
            // ADDITIVE + NULLABLE. snake_case on the wire per spec.
            //
            // These complement (not replace) the camelCase fields above —
            // existing FE callers keep working unchanged. New FE callers can
            // use either family.
            //
            // adr_state            : enum forward-contract. The column is
            //                        TBC; we always return null until the
            //                        trailers schema gains an `adr_state`.
            // active_order_*       : populated by TrailerController::
            //                        enrichTrailers() via synthetic
            //                        __active_order_* attributes; null when
            //                        the trailer has no open loading order.
            // last_loaded_at       : max(completed_at) from loading_operations
            //                        joined by trailer_id (also from
            //                        enrichTrailers()).
            // parking_location     : human label of currentParking — name
            //                        column on the parkings row.
            // current_context_kind : derived enum mirroring deriveContextKind
            //                        on the controller; runs in the resource
            //                        so it stays in sync without a join.
            // -----------------------------------------------------------------
            'adr_state' => null,
            'active_order_id' => $this->getAttribute('__active_order_id'),
            'active_order_code' => $this->getAttribute('__active_order_code'),
            'last_loaded_at' => $this->resolveLastLoadedAt(),
            'parking_location' => $this->resolveParkingLocation(),
            'current_context_kind' => $this->resolveCurrentContextKind(),
        ];
    }

    /**
     * The controller stamps __last_loaded_at as either an ISO string or a
     * Carbon-castable raw datetime depending on driver. Normalise to ISO
     * 8601 so the wire shape is always stable.
     */
    protected function resolveLastLoadedAt(): ?string
    {
        $raw = $this->getAttribute('__last_loaded_at');
        if ($raw === null) return null;
        try {
            return \Illuminate\Support\Carbon::parse($raw)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveParkingLocation(): ?string
    {
        if (! $this->relationLoaded('currentParking')) return null;
        $parking = $this->currentParking;
        return $parking?->name;
    }

    /**
     * Mirror of TrailerController::deriveContextKind so any caller
     * (collection, single resource, export) gets the same value.
     */
    protected function resolveCurrentContextKind(): ?string
    {
        if (! empty($this->getAttribute('__active_order_id'))) {
            return 'loading';
        }
        if (! empty($this->current_parking_id)) {
            return 'parking';
        }
        if ($this->status === TrailerStatus::BLOCKED) {
            return 'maintenance';
        }
        return null;
    }
}
