<?php

namespace App\Enums;

/**
 * What happens operationally when a calibration profile is invalid,
 * missing or overdue (V2.1 §6.2).
 *
 *   warn_only                  — surface a warning; analysis + release continue
 *   block_analysis_start       — refuse to start a new analysis
 *   block_release_certificate  — analysis allowed, but release/certificate blocked
 */
class CalibrationLockoutBehavior
{
    public const WARN_ONLY = 'warn_only';
    public const BLOCK_ANALYSIS_START = 'block_analysis_start';
    public const BLOCK_RELEASE_CERTIFICATE = 'block_release_certificate';

    public static function all(): array
    {
        return [
            self::WARN_ONLY,
            self::BLOCK_ANALYSIS_START,
            self::BLOCK_RELEASE_CERTIFICATE,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::WARN_ONLY => 'Warn only',
            self::BLOCK_ANALYSIS_START => 'Block analysis start',
            self::BLOCK_RELEASE_CERTIFICATE => 'Block release / certificate',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::WARN_ONLY => 'warning',
            self::BLOCK_ANALYSIS_START => 'danger',
            self::BLOCK_RELEASE_CERTIFICATE => 'danger',
            default => 'neutral',
        };
    }
}
