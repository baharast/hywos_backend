<?php

namespace App\Http\Resources;

use App\Enums\GateTerminalCurrentScreen;
use App\Enums\GateTerminalSessionState;
use App\Enums\GateTerminalTouchpoint;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Per-session row + detail shape. Matches the TypeScript types in V2.3 §16
 * (GateTerminalSessionListItem).
 *
 * Optional context blocks (device / driver / plantVisit / order / trailer)
 * are emitted as null when the corresponding *_id is missing so the FE can
 * branch cleanly on presence without checking each sub-field.
 */
class GateTerminalSessionResource extends JsonResource
{
    public function toArray($request): array
    {
        $touchpoint = $this->touchpoint ?? GateTerminalTouchpoint::ENTRY_GATE;
        $state = $this->session_state ?? GateTerminalSessionState::IDLE;
        $screen = $this->current_screen;

        return [
            'id' => $this->id,
            'sessionNo' => $this->session_no,

            'touchpoint' => [
                'value' => $touchpoint,
                'label' => $this->touchpoint_label ?? GateTerminalTouchpoint::label($touchpoint),
            ],

            'device' => $this->device_id ? [
                'id' => $this->device_id,
                'label' => $this->device_label,
                'health' => $this->device_health,
            ] : null,

            'driver' => $this->driver_id ? [
                'id' => $this->driver_id,
                'name' => $this->driver_name,
                'code' => $this->driver_code,
            ] : null,

            'plantVisit' => $this->plant_visit_id ? [
                'id' => $this->plant_visit_id,
                'visitNo' => $this->visit_no,
            ] : null,

            'order' => $this->order_id ? [
                'id' => $this->order_id,
                'orderNo' => $this->order_no,
            ] : null,

            'trailer' => $this->trailer_id ? [
                'id' => $this->trailer_id,
                'label' => $this->trailer_label,
            ] : null,

            'currentScreen' => $screen ? [
                'value' => $screen,
                'label' => GateTerminalCurrentScreen::label($screen),
            ] : null,

            'sessionState' => [
                'value' => $state,
                'label' => GateTerminalSessionState::label($state),
                'tone' => GateTerminalSessionState::tone($state),
            ],

            'issueReason' => $this->issue_reason,
            'actionNeeded' => $this->action_needed,

            // V2.3 §11 — backend-set; never inferred.
            'needsOperator' => (bool) $this->needs_operator,
            'supportRequested' => (bool) $this->support_requested,
            'clarificationCaseId' => $this->clarification_case_id,

            'lastActivityAt' => $this->last_activity_at?->toIso8601String(),
        ];
    }
}
