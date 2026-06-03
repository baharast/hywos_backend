<?php

namespace App\Enums;

/**
 * V1 §7.5 Logbook area / location. Spec values:
 *   Filling Station 01-06, Entry Gate, Exit Gate, Analysis,
 *   Compressor, Electrolyzer, Control Room, Server/Network.
 *
 * Stored as a free-form string but validated against this catalogue
 * at create / correct time. The FE dropdown is sourced from the
 * subset of these values present in the current dataset (V1.4 §3 rule).
 */
class LogbookArea
{
    public const FILLING_STATION_01 = 'filling_station_01';
    public const FILLING_STATION_02 = 'filling_station_02';
    public const FILLING_STATION_03 = 'filling_station_03';
    public const FILLING_STATION_04 = 'filling_station_04';
    public const FILLING_STATION_05 = 'filling_station_05';
    public const FILLING_STATION_06 = 'filling_station_06';
    public const ENTRY_GATE = 'entry_gate';
    public const EXIT_GATE = 'exit_gate';
    public const ANALYSIS = 'analysis';
    public const COMPRESSOR = 'compressor';
    public const ELECTROLYZER = 'electrolyzer';
    public const CONTROL_ROOM = 'control_room';
    public const SERVER_NETWORK = 'server_network';

    public static function all(): array
    {
        return [
            self::FILLING_STATION_01, self::FILLING_STATION_02,
            self::FILLING_STATION_03, self::FILLING_STATION_04,
            self::FILLING_STATION_05, self::FILLING_STATION_06,
            self::ENTRY_GATE, self::EXIT_GATE,
            self::ANALYSIS, self::COMPRESSOR, self::ELECTROLYZER,
            self::CONTROL_ROOM, self::SERVER_NETWORK,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('logbook.area.' . $v);
        return $translated !== 'logbook.area.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
