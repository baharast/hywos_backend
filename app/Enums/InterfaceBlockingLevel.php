<?php

namespace App\Enums;

/**
 * V1.4 §9 — operational consequence of an interface being down. Tells
 * the FE whether to render the row as a hard blocker (`critical` — e.g.
 * SAP order import, PLC fieldbus) or a soft degradation (`non_blocking`
 * — e.g. cloud sync, VPN).
 */
class InterfaceBlockingLevel
{
    public const CRITICAL = 'critical';
    public const OPERATIONAL = 'operational';
    public const REPORTING_ONLY = 'reporting_only';
    public const NON_BLOCKING = 'non_blocking';

    public static function all(): array
    {
        return [
            self::CRITICAL,
            self::OPERATIONAL,
            self::REPORTING_ONLY,
            self::NON_BLOCKING,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.interface_blocking_level.' . $v);
        return $translated !== 'hardware.interface_blocking_level.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::CRITICAL => 'danger',
            self::OPERATIONAL => 'warning',
            self::REPORTING_ONLY => 'info',
            self::NON_BLOCKING => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * Sort priority (lower = top). Critical issues outrank operational,
     * which outrank reporting-only and non-blocking. Used as the second
     * tier of the default sort after status priority.
     */
    public static function displayPriority(string $v): int
    {
        return match ($v) {
            self::CRITICAL => 1,
            self::OPERATIONAL => 2,
            self::REPORTING_ONLY => 3,
            self::NON_BLOCKING => 4,
            default => 9,
        };
    }
}
