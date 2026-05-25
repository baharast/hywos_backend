<?php

namespace App\Http\Resources;

use App\Enums\ParkingLoadState;
use App\Enums\ParkingNextAction;
use App\Enums\ParkingSlotStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class ParkingResource extends JsonResource
{
    public function toArray($request): array
    {
        $slotStatus = $this->slot_status ?: ParkingSlotStatus::FREE;
        $loadState = $this->current_load_state;
        $nextAction = $this->next_action ?: ParkingNextAction::NONE;

        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,

            'siteId' => $this->site_id,
            'plantAreaId' => $this->plant_area_id,
            'plantConfigurationId' => $this->plant_configuration_id,

            'slotStatus' => [
                'value' => $slotStatus,
                'label' => ParkingSlotStatus::label($slotStatus),
                'tone' => ParkingSlotStatus::tone($slotStatus),
            ],

            'currentTrailer' => $this->current_trailer_id ? [
                'id' => $this->current_trailer_id,
                'label' => $this->current_trailer_label,
                'plate' => $this->current_trailer_plate,
                'chip' => $this->current_trailer_chip,
            ] : null,

            'loadState' => $loadState ? [
                'value' => $loadState,
                'label' => ParkingLoadState::label($loadState),
                'tone' => ParkingLoadState::tone($loadState),
            ] : null,

            'linkedOrder' => $this->linked_order_id ? [
                'id' => $this->linked_order_id,
                'orderNo' => $this->linked_order_no,
            ] : null,

            'activeVisit' => $this->active_visit_id ? [
                'id' => $this->active_visit_id,
                'visitNo' => $this->active_visit_no,
            ] : null,

            'driver' => $this->driver_id ? [
                'id' => $this->driver_id,
                'name' => $this->driver_name,
            ] : null,

            'tractorPlate' => $this->tractor_plate,

            'parkedSince' => $this->parked_since?->toIso8601String(),
            'reservedFor' => $this->reserved_for?->toIso8601String(),

            'blockerReason' => $this->blocker_reason,
            'clarificationCaseId' => $this->clarification_case_id,

            'nextAction' => [
                'value' => $nextAction,
                'label' => ParkingNextAction::label($nextAction),
            ],

            // V2.1 §6.4 — only present when LOADED + linked order is known
            'shouldShowDocuments' => (bool) $this->should_show_documents,
            'documentSummary' => $this->document_summary,

            'isActive' => (bool) $this->is_active,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
