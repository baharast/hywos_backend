<?php

namespace App\Enums;

/**
 * V1.4 §9 — current communication / sync state. The display priority
 * matches the page's default sort ("Fault/Critical first, then
 * Operational impact, then oldest unresolved issue"): fault first,
 * online last.
 *
 * `disabled` is distinct from `offline`: disabled means we deliberately
 * stopped polling (e.g. maintenance window); offline means the interface
 * is down without operator intent.
 */
class InterfaceStatus
{
    public const ONLINE = 'online';
    public const WARNING = 'warning';
    public const FAULT = 'fault';
    public const OFFLINE = 'offline';
    public const DISABLED = 'disabled';
    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::ONLINE,
            self::WARNING,
            self::FAULT,
            self::OFFLINE,
            self::DISABLED,
            self::UNKNOWN,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ONLINE => 'Online',
            self::WARNING => 'Warning',
            self::FAULT => 'Fault',
            self::OFFLINE => 'Offline',
            self::DISABLED => 'Disabled',
            self::UNKNOWN => 'Unknown',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ONLINE => 'success',
            self::WARNING => 'warning',
            self::FAULT => 'danger',
            self::OFFLINE => 'offline',
            self::DISABLED => 'maintenance',
            self::UNKNOWN => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * Sort priority for the V1.4 §9 default ordering. Lower number =
     * shown first. Fault and offline crowd to the top so an operator
     * spots blockers before scrolling.
     */
    public static function displayPriority(string $v): int
    {
        return match ($v) {
            self::FAULT => 1,
            self::OFFLINE => 2,
            self::WARNING => 3,
            self::DISABLED => 4,
            self::UNKNOWN => 5,
            self::ONLINE => 6,
            default => 9,
        };
    }
}
