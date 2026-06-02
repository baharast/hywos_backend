<?php

namespace App\Enums;

/**
 * Plant Visit overall status — answers "can this visit continue?"
 *
 * Per Active Plant Visits V1.6 §8. Distinct from `current_step` (which
 * answers "what is happening now?") and `task_flow` (which answers "why is
 * the driver on site?"). Never collapse these three concepts into one
 * badge in the resource.
 *
 * Waiting must always carry a visible `waiting_reason` (see PlantVisit
 * model saving hook).
 */
class PlantVisitStatus
{
    public const NORMAL = 'normal';
    public const WAITING = 'waiting';
    public const CLARIFICATION = 'clarification';
    public const BLOCKED = 'blocked';
    public const READY_FOR_EXIT = 'ready_for_exit';
    public const CLOSED = 'closed';

    public static function all(): array
    {
        return [
            self::NORMAL,
            self::WAITING,
            self::CLARIFICATION,
            self::BLOCKED,
            self::READY_FOR_EXIT,
            self::CLOSED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('plant_visits.visit_status.' . $v);
        return $translated !== 'plant_visits.visit_status.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::NORMAL => 'success',
            self::WAITING => 'warning',
            self::CLARIFICATION => 'orange',
            self::BLOCKED => 'danger',
            self::READY_FOR_EXIT => 'success',
            self::CLOSED => 'offline',
            default => 'neutral',
        };
    }
}
