<?php

namespace App\Enums;

/**
 * V1 §9 alarm status. Active / Acknowledged / Assigned / In Progress /
 * Resolved pending close / Closed / Suppressed Maintenance.
 *
 * V1 §6.9 transitions:
 *   active        → acknowledged (alarm.acknowledge)
 *   *             → assigned (alarm.assign) — owner_role/user set
 *   acknowledged/assigned → in_progress (alarm.mark_in_progress)
 *   in_progress   → resolved_pending_close → closed (alarm.resolve / .close)
 *   any non-closed → closed (when condition cleared + workflow allows)
 */
class AlarmStatus
{
    public const ACTIVE = 'active';
    public const ACKNOWLEDGED = 'acknowledged';
    public const ASSIGNED = 'assigned';
    public const IN_PROGRESS = 'in_progress';
    public const RESOLVED_PENDING_CLOSE = 'resolved_pending_close';
    public const CLOSED = 'closed';
    public const SUPPRESSED_MAINTENANCE = 'suppressed_maintenance';

    public static function all(): array
    {
        return [
            self::ACTIVE, self::ACKNOWLEDGED, self::ASSIGNED,
            self::IN_PROGRESS, self::RESOLVED_PENDING_CLOSE,
            self::CLOSED, self::SUPPRESSED_MAINTENANCE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('alarms.status.' . $v);
        return $translated !== 'alarms.status.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ACTIVE => 'danger',
            self::ACKNOWLEDGED => 'warning',
            self::ASSIGNED, self::IN_PROGRESS => 'info',
            self::RESOLVED_PENDING_CLOSE => 'info',
            self::CLOSED => 'success',
            self::SUPPRESSED_MAINTENANCE => 'neutral',
            default => 'neutral',
        };
    }

    /** Default-list "open" statuses — everything except closed. */
    public static function openStatuses(): array
    {
        return [
            self::ACTIVE, self::ACKNOWLEDGED, self::ASSIGNED,
            self::IN_PROGRESS, self::RESOLVED_PENDING_CLOSE,
            self::SUPPRESSED_MAINTENANCE,
        ];
    }
}
