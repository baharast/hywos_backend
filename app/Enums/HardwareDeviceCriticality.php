<?php

namespace App\Enums;

/**
 * Criticality classification per FillTrack System & Devices V1.4 §4.3.
 *
 * `critical` devices contribute to the Summary Bar's
 * `criticalDeviceBlockers` and `offlineCriticalDevices` counters when
 * unhealthy. Lower criticalities are surfaced in the table but don't
 * elevate the summary headline.
 */
class HardwareDeviceCriticality
{
    public const CRITICAL = 'critical';
    public const HIGH = 'high';
    public const MEDIUM = 'medium';
    public const LOW = 'low';

    public static function all(): array
    {
        return [self::CRITICAL, self::HIGH, self::MEDIUM, self::LOW];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::CRITICAL => 'Critical',
            self::HIGH => 'High',
            self::MEDIUM => 'Medium',
            self::LOW => 'Low',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::CRITICAL => 'danger',
            self::HIGH => 'warning',
            self::MEDIUM => 'info',
            self::LOW => 'neutral',
            default => 'neutral',
        };
    }
}
