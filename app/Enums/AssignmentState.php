<?php

namespace App\Enums;

/**
 * Generic driver/trailer assignment state on a Loading Order.
 *
 * Per Loading Orders V2.2 §4.2 / §4.3, driver assignment and trailer
 * assignment are SEPARATE, distinct states — they must never be merged
 * into one vague "ready" / "assigned" status on the resource.
 */
class AssignmentState
{
    public const ASSIGNED = 'assigned';
    public const NOT_ASSIGNED = 'not_assigned';
    public const NOT_REQUIRED = 'not_required';

    public static function all(): array
    {
        return [self::ASSIGNED, self::NOT_ASSIGNED, self::NOT_REQUIRED];
    }

    public static function label(string $v): string
    {
        $translated = __('loading.assignment_state.' . $v);
        return $translated !== 'loading.assignment_state.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ASSIGNED => 'success',
            self::NOT_ASSIGNED => 'warning',
            self::NOT_REQUIRED => 'neutral',
            default => 'neutral',
        };
    }
}
