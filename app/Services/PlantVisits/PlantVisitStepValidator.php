<?php

namespace App\Services\PlantVisits;

use App\Enums\DriverTask;
use App\Enums\PlantVisitStatus;
use App\Enums\PlantVisitStep;
use App\Models\PlantVisit;

/**
 * Single source of truth for the V1.6 §7.1 allowed-step-by-taskFlow matrix.
 *
 * If the persisted `(task_flow, current_step)` pair is not in ALLOWED_STEPS,
 * the model's saving() hook calls ensureConsistent() which rewrites the row
 * to PlantVisitStatus::CLARIFICATION + blocker_reason='step_mismatch'. We
 * NEVER throw — per V1.6 §7.1 "Data mismatch behavior": the row must still
 * be persisted and made visible in the dashboard as a clarification.
 */
class PlantVisitStepValidator
{
    /**
     * @var array<string, list<string>>
     */
    public const ALLOWED_STEPS = [
        DriverTask::TRAILER_FILLING => [
            PlantVisitStep::REGISTERED,
            PlantVisitStep::TRAILER_IDENTIFICATION,
            PlantVisitStep::TRACTOR_PLATE,
            PlantVisitStep::TASK_SELECTION,
            PlantVisitStep::LOADING,
            PlantVisitStep::WAITING_FOR_ANALYSIS,
            PlantVisitStep::DOCUMENTS,
            PlantVisitStep::EXIT_VALIDATION,
        ],
        DriverTask::PARK_TRAILER => [
            PlantVisitStep::REGISTERED,
            PlantVisitStep::TRAILER_IDENTIFICATION,
            PlantVisitStep::TRACTOR_PLATE,
            PlantVisitStep::TASK_SELECTION,
            PlantVisitStep::PARKING,
            PlantVisitStep::EXIT_VALIDATION,
        ],
        DriverTask::PICKUP_LOADED_TRAILER => [
            PlantVisitStep::REGISTERED,
            PlantVisitStep::TRACTOR_PLATE,
            PlantVisitStep::TASK_SELECTION,
            PlantVisitStep::PICKUP,
            PlantVisitStep::DOCUMENTS,
            PlantVisitStep::EXIT_VALIDATION,
        ],
        DriverTask::PICKUP_EMPTY_TRAILER_THEN_LOAD => [
            PlantVisitStep::REGISTERED,
            PlantVisitStep::TRACTOR_PLATE,
            PlantVisitStep::TASK_SELECTION,
            PlantVisitStep::PICKUP,
            PlantVisitStep::LOADING,
            PlantVisitStep::WAITING_FOR_ANALYSIS,
            PlantVisitStep::DOCUMENTS,
            PlantVisitStep::EXIT_VALIDATION,
        ],
        DriverTask::DOCUMENTS_EXIT => [
            PlantVisitStep::REGISTERED,
            PlantVisitStep::TASK_SELECTION,
            PlantVisitStep::DOCUMENTS,
            PlantVisitStep::EXIT_VALIDATION,
        ],
        DriverTask::EXIT_ONLY => [
            PlantVisitStep::REGISTERED,
            PlantVisitStep::TASK_SELECTION,
            PlantVisitStep::EXIT_VALIDATION,
        ],
    ];

    public function isAllowed(?string $taskFlow, ?string $currentStep): bool
    {
        // If either side is unset we treat the pair as "not yet decided" —
        // not a mismatch. The row is persisted as-is.
        if (is_null($taskFlow) || is_null($currentStep)) {
            return true;
        }
        $allowed = self::ALLOWED_STEPS[$taskFlow] ?? null;
        if (is_null($allowed)) {
            // Unknown task flow → can't validate; let it through.
            return true;
        }
        return in_array($currentStep, $allowed, true);
    }

    /**
     * @return list<string>
     */
    public function allowedStepsFor(?string $taskFlow): array
    {
        if (is_null($taskFlow)) {
            return PlantVisitStep::all();
        }
        return self::ALLOWED_STEPS[$taskFlow] ?? [];
    }

    /**
     * Mutates the visit in-place if `(task_flow, current_step)` is inconsistent.
     * Does NOT throw. Only sets `blocker_reason` if none is already set so
     * we don't clobber a more specific operator-supplied reason.
     */
    public function ensureConsistent(PlantVisit $visit): void
    {
        if ($this->isAllowed($visit->task_flow, $visit->current_step)) {
            return;
        }
        $visit->visit_status = PlantVisitStatus::CLARIFICATION;
        if (empty($visit->blocker_reason)) {
            $visit->blocker_reason = 'step_mismatch';
        }
    }
}
