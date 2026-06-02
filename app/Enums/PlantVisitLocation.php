<?php

namespace App\Enums;

/**
 * Plant Visit current location — strictly the MVP site location set
 * per Active Plant Visits V1.6 §7.1.
 *
 * Do NOT add Car Park, Parking P-12, Document Terminal, or any generic
 * location. Each value here maps to a real physical/logical area in the
 * MVP site model.
 */
class PlantVisitLocation
{
    public const ENTRY_GATE = 'entry_gate';
    public const CONTROL_ROOM_DRIVER_TERMINAL = 'control_room_driver_terminal';
    public const BAY_LINE_1 = 'bay_line_1';
    public const BAY_LINE_2 = 'bay_line_2';
    public const BAY_LINE_3 = 'bay_line_3';
    public const BAY_LINE_4 = 'bay_line_4';
    public const BAY_LINE_5 = 'bay_line_5';
    public const BAY_LINE_6 = 'bay_line_6';
    public const PARKING_1 = 'parking_1';
    public const PARKING_2 = 'parking_2';
    public const EXIT_GATE = 'exit_gate';

    public static function all(): array
    {
        return [
            self::ENTRY_GATE,
            self::CONTROL_ROOM_DRIVER_TERMINAL,
            self::BAY_LINE_1,
            self::BAY_LINE_2,
            self::BAY_LINE_3,
            self::BAY_LINE_4,
            self::BAY_LINE_5,
            self::BAY_LINE_6,
            self::PARKING_1,
            self::PARKING_2,
            self::EXIT_GATE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('plant_visits.location.' . $v);
        return $translated !== 'plant_visits.location.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
