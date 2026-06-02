<?php

namespace App\Enums;

/**
 * Which authentication credential a trailer carries, surfaced on the
 * trailer-assignment picker so the operator can tell at a glance whether a
 * trailer is identified by a fixed RFID chip, a one-time TAN, or both.
 *
 * A trailer is assignable to an order when it carries at least ONE active
 * credential (chip OR tan) — see Trailer::scopeAssignable() /
 * Trailer::getAssignmentBlockReasonAttribute() for the single source of
 * truth. `none` always renders disabled on the picker.
 */
class TrailerCredentialKind
{
    public const CHIP = 'chip';
    public const TAN = 'tan';
    public const BOTH = 'both';
    public const NONE = 'none';

    public static function all(): array
    {
        return [self::CHIP, self::TAN, self::BOTH, self::NONE];
    }

    /**
     * Resolve the kind from the two raw presence booleans. Centralised so
     * the resource and any future caller agree on the chip/tan/both/none
     * collapse.
     */
    public static function resolve(bool $hasChip, bool $hasTan): string
    {
        return match (true) {
            $hasChip && $hasTan => self::BOTH,
            $hasChip => self::CHIP,
            $hasTan => self::TAN,
            default => self::NONE,
        };
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::CHIP => 'Chip',
            self::TAN => 'TAN',
            self::BOTH => 'Chip + TAN',
            self::NONE => 'No credential',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::CHIP, self::TAN, self::BOTH => 'info',
            self::NONE => 'warning',
            default => 'neutral',
        };
    }
}
