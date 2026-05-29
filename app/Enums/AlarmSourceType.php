<?php

namespace App\Enums;

/**
 * V1 §6.6 alarm source type.
 *   Station PLC, analysis PLC, central PLC, gate smart panel,
 *   compressor controller, electrolyzer controller, printer,
 *   SAP connector.
 */
class AlarmSourceType
{
    public const STATION_PLC = 'station_plc';
    public const ANALYSIS_PLC = 'analysis_plc';
    public const CENTRAL_PLC = 'central_plc';
    public const GATE_SMART_PANEL = 'gate_smart_panel';
    public const COMPRESSOR_CONTROLLER = 'compressor_controller';
    public const ELECTROLYZER_CONTROLLER = 'electrolyzer_controller';
    public const PRINTER = 'printer';
    public const SAP_CONNECTOR = 'sap_connector';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::STATION_PLC, self::ANALYSIS_PLC, self::CENTRAL_PLC,
            self::GATE_SMART_PANEL, self::COMPRESSOR_CONTROLLER,
            self::ELECTROLYZER_CONTROLLER, self::PRINTER,
            self::SAP_CONNECTOR, self::OTHER,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::STATION_PLC => 'Station PLC',
            self::ANALYSIS_PLC => 'Analysis PLC',
            self::CENTRAL_PLC => 'Central PLC',
            self::GATE_SMART_PANEL => 'Gate smart panel',
            self::COMPRESSOR_CONTROLLER => 'Compressor controller',
            self::ELECTROLYZER_CONTROLLER => 'Electrolyzer controller',
            self::PRINTER => 'Printer',
            self::SAP_CONNECTOR => 'SAP connector',
            self::OTHER => 'Other',
            default => ucwords(str_replace('_', ' ', $v)),
        };
    }
}
