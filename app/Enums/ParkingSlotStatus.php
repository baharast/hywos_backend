<?php

namespace App\Enums;

/**
 * Trailer Parking slot operational state (V2.1 §6.2).
 *
 * Drives the slot-card colour on the two-slot board and gates which actions
 * the operator may perform on a slot.
 */
class ParkingSlotStatus
{
    public const FREE = 'free';
    public const RESERVED = 'reserved';
    public const OCCUPIED = 'occupied';
    public const BLOCKED = 'blocked';
    public const OUT_OF_SERVICE = 'out_of_service';

    public static function all(): array
    {
        return [
            self::FREE,
            self::RESERVED,
            self::OCCUPIED,
            self::BLOCKED,
            self::OUT_OF_SERVICE,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::FREE => 'Free',
            self::RESERVED => 'Reserved',
            self::OCCUPIED => 'Occupied',
            self::BLOCKED => 'Blocked',
            self::OUT_OF_SERVICE => 'Out of service',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::FREE => 'success',
            self::RESERVED => 'info',
            self::OCCUPIED => 'neutral',
            self::BLOCKED => 'danger',
            self::OUT_OF_SERVICE => 'maintenance',
            default => 'neutral',
        };
    }
}
