<?php

namespace App\Enums;

/**
 * Result of the LATEST identification event at a reader, per V1.4 §7
 * "Last Identification Event" column.
 *
 * Spec lists: "Accepted, Denied, Unknown chip, Multiple matches, Reader
 * error". We add UNKNOWN as a calm fallback when no event data is
 * available (e.g. brand-new reader, or reader is a filling-bay RFID
 * that doesn't sit on a V2.3 touchpoint).
 *
 * V1 sources this best-effort from terminal_sessions.session_state for
 * gate/driver-terminal readers; filling-bay readers stay UNKNOWN until
 * a dedicated identification-events sibling table lands.
 */
class IdentificationResultStatus
{
    public const ACCEPTED = 'accepted';
    public const DENIED = 'denied';
    public const UNKNOWN_CHIP = 'unknown_chip';
    public const MULTIPLE_MATCHES = 'multiple_matches';
    public const READER_ERROR = 'reader_error';
    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::ACCEPTED,
            self::DENIED,
            self::UNKNOWN_CHIP,
            self::MULTIPLE_MATCHES,
            self::READER_ERROR,
            self::UNKNOWN,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ACCEPTED => 'Accepted',
            self::DENIED => 'Denied',
            self::UNKNOWN_CHIP => 'Unknown chip',
            self::MULTIPLE_MATCHES => 'Multiple matches',
            self::READER_ERROR => 'Reader error',
            self::UNKNOWN => 'No recent event',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ACCEPTED => 'success',
            self::DENIED => 'warning',
            self::UNKNOWN_CHIP, self::MULTIPLE_MATCHES => 'warning',
            self::READER_ERROR => 'danger',
            self::UNKNOWN => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * Best-effort map from V2.3 terminal session_state to identification
     * result. Used for entry-gate / exit-gate / driver-terminal readers
     * because they sit on a V2.3 touchpoint.
     */
    public static function fromSessionState(?string $sessionState): string
    {
        return match ($sessionState) {
            'active' => self::ACCEPTED,
            'denied' => self::DENIED,
            'needs_operator' => self::MULTIPLE_MATCHES,
            'device_fault' => self::READER_ERROR,
            default => self::UNKNOWN,
        };
    }
}
