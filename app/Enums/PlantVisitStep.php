<?php

namespace App\Enums;

/**
 * Plant Visit current step — answers "where is the visit in the process?"
 *
 * Per Active Plant Visits V1.6 §7. Validity of a step is constrained by
 * `task_flow` — see PlantVisitStepValidator. Invalid combinations must NOT
 * be silently displayed; the model saving hook rewrites them to
 * PlantVisitStatus::CLARIFICATION + blocker_reason='step_mismatch'.
 */
class PlantVisitStep
{
    public const REGISTERED = 'registered';
    public const TRAILER_IDENTIFICATION = 'trailer_identification';
    public const TRACTOR_PLATE = 'tractor_plate';
    public const TASK_SELECTION = 'task_selection';
    public const PARKING = 'parking';
    public const PICKUP = 'pickup';
    public const LOADING = 'loading';
    public const WAITING_FOR_ANALYSIS = 'waiting_for_analysis';
    public const DOCUMENTS = 'documents';
    public const EXIT_VALIDATION = 'exit_validation';

    public static function all(): array
    {
        return [
            self::REGISTERED,
            self::TRAILER_IDENTIFICATION,
            self::TRACTOR_PLATE,
            self::TASK_SELECTION,
            self::PARKING,
            self::PICKUP,
            self::LOADING,
            self::WAITING_FOR_ANALYSIS,
            self::DOCUMENTS,
            self::EXIT_VALIDATION,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('plant_visits.visit_step.' . $v);
        return $translated !== 'plant_visits.visit_step.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

}
