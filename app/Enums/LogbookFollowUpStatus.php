<?php

namespace App\Enums;

/**
 * V1 §7.5 + §9 Logbook follow-up status. Spec values:
 *   Open, in progress, done, cancelled, overdue.
 *
 * `overdue` is DERIVED (not a stored value): the service flips
 * follow_up_status to `overdue` at read time when `follow_up_due_at`
 * is past and `follow_up_status` is open / in_progress. Stored values
 * are only open / in_progress / done / cancelled.
 */
class LogbookFollowUpStatus
{
    public const OPEN = 'open';
    public const IN_PROGRESS = 'in_progress';
    public const DONE = 'done';
    public const CANCELLED = 'cancelled';
    public const OVERDUE = 'overdue'; // DERIVED

    public static function all(): array
    {
        return [
            self::OPEN, self::IN_PROGRESS, self::DONE,
            self::CANCELLED, self::OVERDUE,
        ];
    }

    /** Stored values only — `overdue` is derived at read time. */
    public static function stored(): array
    {
        return [self::OPEN, self::IN_PROGRESS, self::DONE, self::CANCELLED];
    }

    public static function label(string $v): string
    {
        $translated = __('logbook.follow_up_status.' . $v);
        return $translated !== 'logbook.follow_up_status.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::OPEN => 'info',
            self::IN_PROGRESS => 'info',
            self::DONE => 'success',
            self::CANCELLED => 'neutral',
            self::OVERDUE => 'danger',
            default => 'neutral',
        };
    }
}
