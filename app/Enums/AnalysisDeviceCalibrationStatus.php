<?php

namespace App\Enums;

/**
 * V1 §8.1 — OrthoSmart calibration trust state. Drives the FE's
 * calibration badge on the card AND the "result trust" guidance on the
 * detail panel.
 */
class AnalysisDeviceCalibrationStatus
{
    public const VALID = 'valid';
    public const DUE_SOON = 'due_soon';
    public const OVERDUE = 'overdue';
    public const FAILED = 'failed';
    public const NOT_CONFIGURED = 'not_configured';

    public static function all(): array
    {
        return [
            self::VALID,
            self::DUE_SOON,
            self::OVERDUE,
            self::FAILED,
            self::NOT_CONFIGURED,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::VALID => 'Valid',
            self::DUE_SOON => 'Due soon',
            self::OVERDUE => 'Overdue',
            self::FAILED => 'Failed',
            self::NOT_CONFIGURED => 'Not configured',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::VALID => 'success',
            self::DUE_SOON => 'warning',
            self::OVERDUE, self::FAILED => 'danger',
            self::NOT_CONFIGURED => 'offline',
            default => 'neutral',
        };
    }
}
