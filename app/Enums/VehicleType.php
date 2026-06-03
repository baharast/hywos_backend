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
        $translated = __('vehicles.vehicle_type.' . $value);
        return $translated !== 'vehicles.vehicle_type.' . $value
            ? $translated
            : ucwords(str_replace('_', ' ', $value));
    }
}
