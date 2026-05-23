<?php

namespace App\Enums;

class PlantAreaType
{
    public const GATE_AREA = 'gate_area';
    public const LOADING_AREA = 'loading_area';
    public const PARKING_AREA = 'parking_area';
    public const CONTROL_ROOM = 'control_room';
    public const SERVICE_AREA = 'service_area';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::GATE_AREA,
            self::LOADING_AREA,
            self::PARKING_AREA,
            self::CONTROL_ROOM,
            self::SERVICE_AREA,
            self::OTHER,
        ];
    }
}
