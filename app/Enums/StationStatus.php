<?php

namespace App\Enums;

use App\Models\BayLine;
use App\Models\LoadingOperation;

class StationStatus
{
    public const FREE = 'free';
    public const RESERVED = 'reserved';
    public const OCCUPIED = 'occupied';
    public const LOADING = 'loading';
    public const FAULT = 'fault';
    public const MAINTENANCE = 'maintenance';
    public const OFFLINE = 'offline';

    public static function all(): array
    {
        return [
            self::FREE, self::RESERVED, self::OCCUPIED, self::LOADING,
            self::FAULT, self::MAINTENANCE, self::OFFLINE,
        ];
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::FREE => 'success',
            self::RESERVED, self::LOADING => 'info',
            self::OCCUPIED => 'warning',
            self::FAULT => 'danger',
            self::MAINTENANCE => 'maintenance',
            self::OFFLINE => 'offline',
            default => 'neutral',
        };
    }

    public static function label(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }

    /**
     * Derive the station's operational status from the BayLine's own status_code
     * combined with whatever active LoadingOperation occupies it.
     *
     * Precedence:
     *   1. BayLine flagged as fault/maintenance/offline overrides everything.
     *   2. Active loading drives the status (LOADING / OCCUPIED / RESERVED).
     *   3. Fall back to BayLine's own status_code, defaulting to FREE.
     */
    public static function derive(BayLine $bay, ?LoadingOperation $active): string
    {
        $code = strtolower((string) ($bay->status_code ?? 'free'));

        if (in_array($code, [self::FAULT, self::MAINTENANCE, self::OFFLINE], true)) {
            return $code;
        }

        if ($active) {
            return match ($active->loading_status) {
                LoadingStatus::LOADING => self::LOADING,
                LoadingStatus::ASSIGNED, LoadingStatus::WAITING_PRE_ANALYSIS,
                LoadingStatus::RELEASED => self::RESERVED,
                LoadingStatus::PAUSED, LoadingStatus::WAITING_MAIN_ANALYSIS,
                LoadingStatus::QUALITY_CHECK_OPEN, LoadingStatus::QUALITY_BLOCKED,
                LoadingStatus::CLARIFICATION_REQUIRED => self::OCCUPIED,
                default => self::OCCUPIED,
            };
        }

        return in_array($code, self::all(), true) ? $code : self::FREE;
    }
}
