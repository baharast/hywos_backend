<?php

namespace App\Enums;

/**
 * Catalogue of canonical physical locations from FillTrack System &
 * Devices V1.4 §3.
 *
 * Spec §3 + §11 rule: filter dropdown options must be derived from the
 * CURRENT TAB DATASET. This enum is the CATALOGUE; the list endpoint
 * returns only the subset of values that have at least one device row
 * in the current results (via `availableFilterValues.locations`).
 *
 * Filling bays are bay 01-06 per spec §3 + §5 (six bay panels).
 */
class HardwarePhysicalLocation
{
    public const ENTRY_GATE = 'entry_gate';
    public const EXIT_GATE = 'exit_gate';
    public const DRIVER_TERMINAL = 'driver_terminal';
    public const FILLING_BAY_01 = 'filling_bay_01';
    public const FILLING_BAY_02 = 'filling_bay_02';
    public const FILLING_BAY_03 = 'filling_bay_03';
    public const FILLING_BAY_04 = 'filling_bay_04';
    public const FILLING_BAY_05 = 'filling_bay_05';
    public const FILLING_BAY_06 = 'filling_bay_06';
    public const CONTROL_ROOM = 'control_room';
    public const TECHNICAL_ROOM = 'technical_room';
    public const ON_SITE_SERVER_ZONE = 'on_site_server_zone';
    public const COMPRESSOR_CONTAINER = 'compressor_container';
    public const ELECTROLYZER_CONTAINER = 'electrolyzer_container';
    public const STORAGE_AREA = 'storage_area';
    public const UTILITY_AREA = 'utility_area';

    public static function all(): array
    {
        return [
            self::ENTRY_GATE, self::EXIT_GATE, self::DRIVER_TERMINAL,
            self::FILLING_BAY_01, self::FILLING_BAY_02, self::FILLING_BAY_03,
            self::FILLING_BAY_04, self::FILLING_BAY_05, self::FILLING_BAY_06,
            self::CONTROL_ROOM, self::TECHNICAL_ROOM, self::ON_SITE_SERVER_ZONE,
            self::COMPRESSOR_CONTAINER, self::ELECTROLYZER_CONTAINER,
            self::STORAGE_AREA, self::UTILITY_AREA,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.physical_location.' . $v);
        return $translated !== 'hardware.physical_location.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
