<?php

namespace App\Enums;

/**
 * Lifecycle status for a Calibration Profile (V2.1 §9 + §6 status model).
 *
 * Mirrors ProductSpecStatus values but kept as a separate class so the
 * domains stay legible in code and audit logs.
 *
 * This is the PROFILE-LEVEL lifecycle (draft → active → inactive →
 * retired). It is distinct from `calibration_status` on the same row,
 * which captures the operational health of the calibration (valid, due
 * soon, overdue, failed, not_configured) — see V2.1 §6.1.
 */
class CalibrationProfileStatus
{
    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const RETIRED = 'retired';

    public static function all(): array
    {
        return [self::DRAFT, self::ACTIVE, self::INACTIVE, self::RETIRED];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::DRAFT => 'Draft',
            self::ACTIVE => 'Active',
            self::INACTIVE => 'Inactive',
            self::RETIRED => 'Retired',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::DRAFT => 'info',
            self::ACTIVE => 'success',
            self::INACTIVE => 'neutral',
            self::RETIRED => 'offline',
            default => 'neutral',
        };
    }

    public static function isEditable(string $v): bool
    {
        return in_array($v, [self::DRAFT, self::ACTIVE], true);
    }

    public static function requiresReasonForEdit(string $v): bool
    {
        return $v === self::ACTIVE;
    }

    /**
     * Operational calibration_status values per V2.1 §6.1. These are NOT
     * the lifecycle of the profile itself; they describe the health of
     * the calibration (valid until next-due, due-soon, overdue, etc.).
     */
    public const CALIBRATION_STATUS_VALID = 'valid';
    public const CALIBRATION_STATUS_DUE_SOON = 'due_soon';
    public const CALIBRATION_STATUS_OVERDUE = 'overdue';
    public const CALIBRATION_STATUS_FAILED = 'failed';
    public const CALIBRATION_STATUS_NOT_CONFIGURED = 'not_configured';

    public static function calibrationStatusAll(): array
    {
        return [
            self::CALIBRATION_STATUS_VALID,
            self::CALIBRATION_STATUS_DUE_SOON,
            self::CALIBRATION_STATUS_OVERDUE,
            self::CALIBRATION_STATUS_FAILED,
            self::CALIBRATION_STATUS_NOT_CONFIGURED,
        ];
    }

    public static function calibrationStatusLabel(string $v): string
    {
        return match ($v) {
            self::CALIBRATION_STATUS_VALID => 'Valid',
            self::CALIBRATION_STATUS_DUE_SOON => 'Due soon',
            self::CALIBRATION_STATUS_OVERDUE => 'Overdue',
            self::CALIBRATION_STATUS_FAILED => 'Failed',
            self::CALIBRATION_STATUS_NOT_CONFIGURED => 'Not configured',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function calibrationStatusTone(string $v): string
    {
        return match ($v) {
            self::CALIBRATION_STATUS_VALID => 'success',
            self::CALIBRATION_STATUS_DUE_SOON => 'warning',
            self::CALIBRATION_STATUS_OVERDUE => 'danger',
            self::CALIBRATION_STATUS_FAILED => 'danger',
            self::CALIBRATION_STATUS_NOT_CONFIGURED => 'warning',
            default => 'neutral',
        };
    }
}
