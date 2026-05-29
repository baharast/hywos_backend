<?php

namespace App\Enums;

/**
 * V1 §7.5 + §9 Logbook severity. Spec values:
 *   Critical, High, Medium, Low, Info.
 *
 * Stored on logbook_entries.severity. The same 5-value vocabulary is
 * shared with §6 Active Alarms and §7.6 Security Events — keep them
 * aligned.
 */
class LogbookSeverity
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
}
