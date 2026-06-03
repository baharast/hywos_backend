<?php

namespace App\Enums;

/**
 * V1 §7.6 Security risk level. Spec §9: Critical, High, Medium, Low, Info.
 *
 * Not stored as a dedicated column on event_logs — V1 derives it from
 * (event_type, severity) at read time. See SecurityEventsService.
 */
class SecurityRiskLevel
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
        $translated = __('terminal.security_risk_level.' . $v);
        return $translated !== 'terminal.security_risk_level.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
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
}
