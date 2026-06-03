<?php

namespace App\Enums;

class VehicleStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const BLOCKED = 'blocked';
    public const ARCHIVED = 'archived';

    public static function all(): array
    {
        return [self::ACTIVE, self::INACTIVE, self::BLOCKED, self::ARCHIVED];
    }

    public static function tone(string $value): string
    {
        return match ($value) {
            self::ACTIVE => 'success',
            self::INACTIVE => 'offline',
            self::BLOCKED => 'danger',
            self::ARCHIVED => 'neutral',
            default => 'neutral',
        };
    }

    public static function label(string $value): string
    {
        $translated = __('vehicles.vehicle_status.' . $value);
        return $translated !== 'vehicles.vehicle_status.' . $value
            ? $translated
            : ucfirst($value);
    }

}
