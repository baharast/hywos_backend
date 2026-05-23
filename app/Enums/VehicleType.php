<?php

namespace App\Enums;

class VehicleType
{
    public const TRACTOR_UNIT = 'tractor_unit';
    public const TRUCK = 'truck';
    public const SERVICE_VEHICLE = 'service_vehicle';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [self::TRACTOR_UNIT, self::TRUCK, self::SERVICE_VEHICLE, self::OTHER];
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::TRACTOR_UNIT => 'Tractor unit',
            self::TRUCK => 'Truck',
            self::SERVICE_VEHICLE => 'Service vehicle',
            self::OTHER => 'Other',
            default => ucwords(str_replace('_', ' ', $value)),
        };
    }
}
