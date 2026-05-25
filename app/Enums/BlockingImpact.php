<?php

namespace App\Enums;

/**
 * What process step the clarification case is currently blocking.
 *
 * Per FillTrack Clarification Cases UX Frontend Spec-EN-V1.3 §4.2.
 *
 * `none` is the calm state used for informational cases or for cases that
 * have been resolved — the default active view should not prioritize them.
 */
class BlockingImpact
{
    public const ENTRY_BLOCKED = 'entry_blocked';
    public const REGISTRATION_BLOCKED = 'registration_blocked';
    public const PARKING_BLOCKED = 'parking_blocked';
    public const LOADING_BLOCKED = 'loading_blocked';
    public const DOCUMENTS_BLOCKED = 'documents_blocked';
    public const EXIT_BLOCKED = 'exit_blocked';
    public const NONE = 'none';

    public static function all(): array
    {
        return [
            self::ENTRY_BLOCKED,
            self::REGISTRATION_BLOCKED,
            self::PARKING_BLOCKED,
            self::LOADING_BLOCKED,
            self::DOCUMENTS_BLOCKED,
            self::EXIT_BLOCKED,
            self::NONE,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ENTRY_BLOCKED => 'Entry blocked',
            self::REGISTRATION_BLOCKED => 'Registration blocked',
            self::PARKING_BLOCKED => 'Parking blocked',
            self::LOADING_BLOCKED => 'Loading blocked',
            self::DOCUMENTS_BLOCKED => 'Documents blocked',
            self::EXIT_BLOCKED => 'Exit blocked',
            self::NONE => 'No current block',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ENTRY_BLOCKED,
            self::REGISTRATION_BLOCKED,
            self::PARKING_BLOCKED,
            self::LOADING_BLOCKED,
            self::DOCUMENTS_BLOCKED,
            self::EXIT_BLOCKED => 'danger',
            self::NONE => 'success',
            default => 'neutral',
        };
    }

    /**
     * Does this impact value currently block an operational process?
     * Useful for the `blockingOpen` summary count and the UI banner gating.
     */
    public static function isBlocking(?string $v): bool
    {
        return $v !== null && $v !== self::NONE;
    }
}
