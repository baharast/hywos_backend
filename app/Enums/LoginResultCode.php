<?php

namespace App\Enums;

/**
 * Outcome of a driver terminal login attempt.
 *
 * Per FillTrack Driver Login Page UX Frontend Spec-EN-V3 §17 (validation
 * and error rules) and §21.2 (`LoginResultCode`). These map 1:1 to the
 * driver-facing copy in §17 — the FE renders the localized message based
 * on the returned code, never on the raw backend description.
 */
class LoginResultCode
{
    // Success path
    public const SUCCESS = 'success';
    public const TRAINING_REQUIRED = 'training_required';

    // Credential issues
    public const INVALID = 'invalid';                      // TAN/chip unknown
    public const EXPIRED = 'expired';                      // TAN past expires_at
    public const USED = 'used';                            // TAN already consumed
    public const REVOKED = 'revoked';                      // TAN revoked
    public const PENDING = 'pending';                      // TAN before valid_from

    // Driver issues
    public const DRIVER_BLOCKED = 'driver_blocked';
    public const DRIVER_INACTIVE = 'driver_inactive';

    // Chip card issues
    public const CHIP_BLOCKED = 'chip_blocked';
    public const CHIP_UNASSIGNED = 'chip_unassigned';
    public const READER_OFFLINE = 'reader_offline';

    // Terminal / infra issues
    public const TERMINAL_OFFLINE = 'terminal_offline';
    public const TERMINAL_SERVICE_MODE = 'terminal_service_mode';
    public const BACKEND_UNAVAILABLE = 'backend_unavailable';
    public const TOO_MANY_ATTEMPTS = 'too_many_attempts';

    public static function all(): array
    {
        return [
            self::SUCCESS,
            self::TRAINING_REQUIRED,
            self::INVALID,
            self::EXPIRED,
            self::USED,
            self::REVOKED,
            self::PENDING,
            self::DRIVER_BLOCKED,
            self::DRIVER_INACTIVE,
            self::CHIP_BLOCKED,
            self::CHIP_UNASSIGNED,
            self::READER_OFFLINE,
            self::TERMINAL_OFFLINE,
            self::TERMINAL_SERVICE_MODE,
            self::BACKEND_UNAVAILABLE,
            self::TOO_MANY_ATTEMPTS,
        ];
    }

    /**
     * Returns true when the code represents a successful identification —
     * `success` (training valid) or `training_required` (identified but
     * blocked from operational continuation).
     */
    public static function isIdentified(string $v): bool
    {
        return in_array($v, [self::SUCCESS, self::TRAINING_REQUIRED], true);
    }

    /**
     * Driver-facing message per V3 §17. The FE may override with a
     * localized copy table — backend message is the safe English default.
     */
    public static function message(string $v): string
    {
        return match ($v) {
            self::SUCCESS => 'Identity confirmed.',
            self::TRAINING_REQUIRED => 'Safety training is required before operational access.',
            self::INVALID => 'Identification is invalid. Please check the number or contact terminal support.',
            self::EXPIRED => 'This TAN has expired. Please contact terminal support.',
            self::USED => 'This TAN has already been used. Please contact terminal support.',
            self::REVOKED => 'This TAN has been revoked. Please contact terminal support.',
            self::PENDING => 'This TAN is not yet valid. Please try again later.',
            self::DRIVER_BLOCKED => 'Access not permitted. Please contact terminal support.',
            self::DRIVER_INACTIVE => 'Access not permitted. Please contact terminal support.',
            self::CHIP_BLOCKED => 'Chip card is blocked. Please contact terminal support.',
            self::CHIP_UNASSIGNED => 'Chip card not recognized. Please contact terminal support.',
            self::READER_OFFLINE => 'Chip reader unavailable. Use TAN or contact terminal support.',
            self::TERMINAL_OFFLINE => 'Terminal is offline. Please contact terminal support.',
            self::TERMINAL_SERVICE_MODE => 'Terminal is in service mode. Please contact terminal support.',
            self::BACKEND_UNAVAILABLE => 'System connection unavailable. Please contact terminal support.',
            self::TOO_MANY_ATTEMPTS => 'Too many attempts. Please contact terminal support.',
            default => 'Login failed. Please contact terminal support.',
        };
    }

    /**
     * Tone for badge display in operator-facing monitor tools. The driver
     * terminal itself uses Design System status badges, not these tones.
     */
    public static function tone(string $v): string
    {
        return match ($v) {
            self::SUCCESS => 'success',
            self::TRAINING_REQUIRED => 'warning',
            self::READER_OFFLINE, self::TERMINAL_OFFLINE => 'offline',
            self::TERMINAL_SERVICE_MODE => 'maintenance',
            default => 'danger',
        };
    }
}
