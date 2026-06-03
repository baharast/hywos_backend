<?php

namespace App\Enums;

/**
 * Operational purpose tag for an issued TAN. Stored on
 * `auth_media.tan_purpose` so a single order can carry multiple TANs
 * (gate-entry credential, filling-station credential, etc.) without
 * the FE having to guess which is which.
 */
class TanPurpose
{
    /** Identity credential at the gate / driver terminal. */
    public const GATE_ENTRY = 'gate_entry';

    /** Bayline filling-station credential issued after order confirm. */
    public const FILLING = 'filling';

    /** Master-data TAN not tied to a specific operational flow. */
    public const GENERAL = 'general';

    public static function all(): array
    {
        return [self::GATE_ENTRY, self::FILLING, self::GENERAL];
    }

    public static function label(string $v): string
    {
        $translated = __('vehicles.tan_purpose.' . $v);
        return $translated !== 'vehicles.tan_purpose.' . $v
            ? $translated
            : ucwords(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::GATE_ENTRY => 'info',
            self::FILLING => 'success',
            self::GENERAL => 'neutral',
            default => 'neutral',
        };
    }
}
