<?php

namespace App\Enums;

/**
 * V1 §12 — Device Status Model. The single source of truth for the per-
 * device health badge tone AND the card-sorting priority on the dashboard.
 *
 * Note: V1 §16 TypeScript types lump `offline` and `stale` into a single
 * `offline_stale` value, and `maintenance` and `inhibited` into
 * `maintenance_inhibited`. Server-side we keep them separate (cleaner
 * filtering / DB queries) and the resource layer can collapse them if a
 * future FE prefers the merged shape.
 */
class AnalysisDeviceHealthStatus
{
    public const HEALTHY = 'healthy';
    public const WARNING = 'warning';
    public const ALARM = 'alarm';
    public const FAULT = 'fault';
    public const OFFLINE = 'offline';
    public const MAINTENANCE = 'maintenance';

    public static function all(): array
    {
        return [
            self::HEALTHY,
            self::WARNING,
            self::ALARM,
            self::FAULT,
            self::OFFLINE,
            self::MAINTENANCE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.device_health.' . $v);
        return $translated !== 'analysis.device_health.' . $v ? $translated : ucfirst($v);
    }

    /**
     * V1 §17 — abnormal states use color, healthy stays quiet. Maintenance
     * gets its own tone so the dashboard can distinguish "this is paused,
     * not broken" from "this needs help".
     */
    public static function tone(string $v): string
    {
        return match ($v) {
            self::HEALTHY => 'success',
            self::WARNING => 'warning',
            self::ALARM, self::FAULT => 'danger',
            self::OFFLINE => 'offline',
            self::MAINTENANCE => 'maintenance',
            default => 'neutral',
        };
    }

    /**
     * V1 §8 — "abnormal states first in visual priority". Lower number =
     * shown first on the cards row.
     *
     *   1: alarm / fault   — safety-relevant, get them in the operator's face
     *   2: warning         — non-blocking but visible
     *   3: maintenance     — paused on purpose
     *   4: offline         — stale heartbeat
     *   5: healthy         — quiet
     */
    public static function displayPriority(string $v): int
    {
        return match ($v) {
            self::ALARM, self::FAULT => 1,
            self::WARNING => 2,
            self::MAINTENANCE => 3,
            self::OFFLINE => 4,
            self::HEALTHY => 5,
            default => 9,
        };
    }
}
