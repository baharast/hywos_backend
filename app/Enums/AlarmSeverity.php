<?php

namespace App\Enums;

/** V1 §6.6 + §9 alarm severity. Critical / High / Medium / Low / Info. */
class AlarmSeverity
{
    public const CRITICAL = 'critical';
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';
    public const INFO = 'info';

    public static function all(): array
    {
        return [self::CRITICAL, self::HIGH, self::MEDIUM, self::LOW, self::INFO];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::CRITICAL => 'Critical',
            self::HIGH => 'High',
            self::MEDIUM => 'Medium',
            self::LOW => 'Low',
            self::INFO => 'Info',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::CRITICAL, self::HIGH => 'danger',
            self::MEDIUM => 'warning',
            self::LOW => 'info',
            self::INFO => 'neutral',
            default => 'neutral',
        };
    }

    /** Lower = higher priority for default sort. */
    public static function displayPriority(string $v): int
    {
        return match ($v) {
            self::CRITICAL => 1,
            self::HIGH => 2,
            self::MEDIUM => 3,
            self::LOW => 4,
            self::INFO => 5,
            default => 9,
        };
    }

    /** V1 §6.9: High/Critical must be acknowledged before close. */
    public static function requiresAcknowledgementBeforeClose(string $v): bool
    {
        return in_array($v, [self::CRITICAL, self::HIGH], true);
    }

    /** V1 §6.9: High/Critical require resolution reason on close. */
    public static function requiresResolutionReason(string $v): bool
    {
        return in_array($v, [self::CRITICAL, self::HIGH], true);
    }
}
