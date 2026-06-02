<?php

namespace App\Enums;

/**
 * Load state of the trailer currently parked in a slot (V2.1 §6.3).
 *
 * Separate from ParkingSlotStatus: a slot may be OCCUPIED with a LOADED or
 * EMPTY trailer; the two badges are rendered side-by-side on the card.
 */
class ParkingLoadState
{
    public const EMPTY = 'empty';
    public const LOADED = 'loaded';
    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [
            self::EMPTY,
            self::LOADED,
            self::UNKNOWN,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('plant_visits.parking_load_state.' . $v);
        return $translated !== 'plant_visits.parking_load_state.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::EMPTY => 'neutral',
            self::LOADED => 'success',
            self::UNKNOWN => 'orange',
            default => 'neutral',
        };
    }
}
