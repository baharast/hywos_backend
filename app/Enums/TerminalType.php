<?php

namespace App\Enums;

class TerminalType
{
    public const DRIVER_TERMINAL = 'driver_terminal';
    public const GATE_TERMINAL = 'gate_terminal';
    public const FILLING_STATION_PANEL = 'filling_station_panel';
    public const OPERATOR_PANEL = 'operator_panel';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::DRIVER_TERMINAL,
            self::GATE_TERMINAL,
            self::FILLING_STATION_PANEL,
            self::OPERATOR_PANEL,
            self::OTHER,
        ];
    }
}
