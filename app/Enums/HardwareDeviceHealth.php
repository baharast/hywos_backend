<?php

namespace App\Enums;

/**
 * Device health values per FillTrack System & Devices V1.4 §3 (Filter
 * Value Source Mapping — Health row).
 */
class HardwareDeviceHealth
{
    public const ONLINE = 'online';
    public const WARNING = 'warning';
    public const ALARM = 'alarm';
    public const FAULT = 'fault';
    public const OFFLINE = 'offline';
    public const SERVICE_MODE = 'service_mode';
    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::ONLINE, self::WARNING, self::ALARM, self::FAULT,
            self::OFFLINE, self::SERVICE_MODE, self::UNKNOWN,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.device_health.' . $v);
        return $translated !== 'hardware.device_health.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ONLINE => 'success',
            self::WARNING => 'warning',
            self::ALARM, self::FAULT => 'danger',
            self::OFFLINE => 'offline',
            self::SERVICE_MODE => 'maintenance',
            self::UNKNOWN => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * V1.4 §9 default-sort priority (lower = sort first).
     */
    public static function displayPriority(string $v): int
    {
        return match ($v) {
            self::ALARM, self::FAULT => 1,
            self::OFFLINE => 2,
            self::WARNING => 3,
            self::SERVICE_MODE => 4,
            self::UNKNOWN => 5,
            self::ONLINE => 6,
            default => 99,
        };
    }

    public static function isHealthy(string $v): bool
    {
        return $v === self::ONLINE;
    }

    /**
     * Used by Operational Summary Bar counters: anything that should
     * draw the operator's eye.
     */
    public static function isAbnormal(string $v): bool
    {
        return in_array($v, [
            self::WARNING, self::ALARM, self::FAULT,
            self::OFFLINE, self::UNKNOWN,
        ], true);
    }
}
