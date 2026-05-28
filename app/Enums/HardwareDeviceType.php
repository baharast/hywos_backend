<?php

namespace App\Enums;

/**
 * Master device-type catalogue from FillTrack System & Devices V1.4 §3.
 *
 * Protocol values (OPC UA, Profinet, etc.) are NOT device types — they
 * live on `hardware_devices.protocol`. See spec §11 "Device Registry filter
 * order".
 */
class HardwareDeviceType
{
    public const PLC = 'plc';
    public const HMI_PANEL = 'hmi_panel';
    public const SMART_GATE_CONTROLLER = 'smart_gate_controller';
    public const PRINTER = 'printer';
    public const RFID_READER = 'rfid_reader';
    public const TRAILER_CHIP_READER = 'trailer_chip_reader';
    public const COMPRESSOR_CONTROLLER = 'compressor_controller';
    public const ELECTROLYZER_CONTROLLER = 'electrolyzer_controller';
    public const ANALYZER = 'analyzer';
    public const NETWORK_DEVICE = 'network_device';
    public const SERVER = 'server';
    public const RIO_CABINET = 'rio_cabinet';
    public const SAFETY_DEVICE = 'safety_device';

    public static function all(): array
    {
        return [
            self::PLC, self::HMI_PANEL, self::SMART_GATE_CONTROLLER,
            self::PRINTER, self::RFID_READER, self::TRAILER_CHIP_READER,
            self::COMPRESSOR_CONTROLLER, self::ELECTROLYZER_CONTROLLER,
            self::ANALYZER, self::NETWORK_DEVICE, self::SERVER,
            self::RIO_CABINET, self::SAFETY_DEVICE,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::PLC => 'PLC',
            self::HMI_PANEL => 'HMI Panel',
            self::SMART_GATE_CONTROLLER => 'Smart Gate Controller',
            self::PRINTER => 'Printer',
            self::RFID_READER => 'RFID Reader',
            self::TRAILER_CHIP_READER => 'Trailer Chip Reader',
            self::COMPRESSOR_CONTROLLER => 'Compressor Controller',
            self::ELECTROLYZER_CONTROLLER => 'Electrolyzer Controller',
            self::ANALYZER => 'Analyzer',
            self::NETWORK_DEVICE => 'Network Device',
            self::SERVER => 'Server',
            self::RIO_CABINET => 'Remote I/O Cabinet',
            self::SAFETY_DEVICE => 'Safety Device',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    /**
     * Maps to the Operational Summary Bar buckets (V1.4 §4.2).
     */
    public static function groupingHint(string $v): string
    {
        return match ($v) {
            self::PRINTER => 'printer_impact',
            self::RFID_READER, self::TRAILER_CHIP_READER => 'reader_impact',
            self::HMI_PANEL, self::SMART_GATE_CONTROLLER => 'panel',
            self::PLC, self::RIO_CABINET => 'controller',
            self::COMPRESSOR_CONTROLLER => 'compressor',
            self::ELECTROLYZER_CONTROLLER => 'electrolyzer',
            self::ANALYZER => 'analysis',
            self::NETWORK_DEVICE, self::SERVER => 'infrastructure',
            self::SAFETY_DEVICE => 'safety',
            default => 'other',
        };
    }
}
