<?php

namespace App\Http\Resources;

use App\Enums\DriverTask;
use App\Enums\PlantVisitLocation;
use App\Enums\PlantVisitStatus;
use App\Enums\PlantVisitStep;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plant Visit resource — mirrors the §12 contract in 16-plant-visits.md.
 *
 * The three workflow concepts (taskFlow / currentStep / status) MUST stay
 * separate per V1.6 §2. status is the only one with a tone; taskFlow,
 * currentStep and currentLocation render as `{value, label}` only.
 */
class PlantVisitResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->visit_status;
        $entry = $this->entry_time;
        $exit = $this->exit_time;

        // durationMinutes — server-derived per §12.
        $durationMinutes = null;
        if ($entry) {
            $end = $exit ?: now();
            $durationMinutes = max(0, (int) $entry->diffInMinutes($end));
        }

        return [
            'id' => $this->id,
            'visitNo' => $this->visit_no,

            'driver' => [
                'id' => $this->driver_id,
                'name' => $this->driver_name,
                'code' => $this->driver_code,
            ],
            'tractor' => [
                'vehicleId' => $this->tractor_vehicle_id,
                'plate' => $this->tractor_plate, // authoritative during visit — V1.6 §7
            ],
            'trailer' => [
                'id' => $this->trailer_id,
                'label' => $this->trailer_label,
                'plate' => $this->trailer_plate,
                'chip' => $this->trailer_chip, // masked display only
            ],
            'order' => [
                'id' => $this->order_id,
                'orderNo' => $this->order_no,
                'sapReference' => $this->sap_reference,
            ],

            'taskFlow' => $this->task_flow ? [
                'value' => $this->task_flow,
                'label' => DriverTask::label($this->task_flow),
            ] : null,
            'currentStep' => $this->current_step ? [
                'value' => $this->current_step,
                'label' => PlantVisitStep::label($this->current_step),
            ] : null,
            'status' => [
                'value' => $status,
                'label' => PlantVisitStatus::label($status),
                'tone' => PlantVisitStatus::tone($status),
            ],
            'currentLocation' => $this->current_location ? [
                'value' => $this->current_location,
                'label' => PlantVisitLocation::label($this->current_location),
            ] : null,

            'nextAction' => [
                'label' => $this->next_action_label,
                'target' => $this->next_action_target,
            ],

            'waitingReason' => $this->waiting_reason,
            'blockerReason' => $this->blocker_reason,
            'ownerRole' => $this->owner_role,
            'clarificationCaseId' => $this->clarification_case_id,

            'isOpen' => $status !== PlantVisitStatus::CLOSED,
            'entryTime' => $entry?->toIso8601String(),
            'exitTime' => $exit?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),
            'closedBy' => $this->closed_by_user_id,
            'closureReason' => $this->closure_reason,
            'durationMinutes' => $durationMinutes,

            'snapshots' => [
                'driver' => $this->driver_snapshot,
                'tractor' => $this->tractor_snapshot,
                'trailer' => $this->trailer_snapshot,
                'order' => $this->order_snapshot,
            ],

            'correlationId' => $this->correlation_id,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
