<?php

namespace App\Enums;

/**
 * Subsystem grouping per FillTrack System & Devices V1.4 §3.
 *
 * Subsystem answers "which functional area" the device belongs to. It is
 * SEPARATE from device_type and physical_location.
 */
class HardwareDeviceSubsystem
{
    public const FILLING = 'filling';
    public const GATE = 'gate';
    public const DRIVER_TERMINAL = 'driver_terminal';
    public const ANALYSIS = 'analysis';
    public const COMPRESSOR = 'compressor';
    public const ELECTROLYZER = 'electrolyzer';
    public const STORAGE = 'storage';
    public const AIR = 'air';
    public const NITROGEN = 'nitrogen';
    public const PRINTING = 'printing';
    public const SAP = 'sap';
    public const CLOUD = 'cloud';
    public const NETWORK = 'network';
    public const INTEGRATION = 'integration';
    public const SAFETY = 'safety';

    public static function all(): array
    {
        return [
            self::FILLING, self::GATE, self::DRIVER_TERMINAL, self::ANALYSIS,
            self::COMPRESSOR, self::ELECTROLYZER, self::STORAGE, self::AIR,
            self::NITROGEN, self::PRINTING, self::SAP, self::CLOUD,
            self::NETWORK, self::INTEGRATION, self::SAFETY,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.device_subsystem.' . $v);
        return $translated !== 'hardware.device_subsystem.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }
}
