<?php

namespace App\Enums;

/**
 * DERIVED controller group for V1.4 §8 PLC / OPC UA Health tab.
 *
 * NOT a stored column — composed at read time from (device_type,
 * subsystem, physical_location). Spec §8's "Endpoint / Controller
 * Group" filter values:
 *   Overall PLC, Main Filling PLC, Filling Bay PLC 01-06,
 *   Analysis Unit 52, Compressor A/B, Compressor D/E,
 *   Electrolyzer 10A/10B, Gate Controllers, Remote I/O.
 *
 * Per V1.4 §3 + §11, the dropdown is derived from the current dataset
 * (only groups with at least one device row appear).
 */
class PlcControllerGroup
{
    public const OVERALL_PLC = 'overall_plc';
    public const MAIN_FILLING_PLC = 'main_filling_plc';
    public const FILLING_BAY_PLC = 'filling_bay_plc';
    public const ANALYSIS_UNIT = 'analysis_unit';
    public const COMPRESSOR_AB = 'compressor_ab';
    public const COMPRESSOR_DE = 'compressor_de';
    public const ELECTROLYZER = 'electrolyzer';
    public const GATE_CONTROLLER = 'gate_controller';
    public const REMOTE_IO = 'remote_io';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::OVERALL_PLC, self::MAIN_FILLING_PLC, self::FILLING_BAY_PLC,
            self::ANALYSIS_UNIT, self::COMPRESSOR_AB, self::COMPRESSOR_DE,
            self::ELECTROLYZER, self::GATE_CONTROLLER, self::REMOTE_IO,
            self::OTHER,
        ];
    }

    /**
     * The 9 in-scope groups; `other` is excluded from default list +
     * filter dropdowns.
     */
    public static function inScopeGroups(): array
    {
        return [
            self::OVERALL_PLC, self::MAIN_FILLING_PLC, self::FILLING_BAY_PLC,
            self::ANALYSIS_UNIT, self::COMPRESSOR_AB, self::COMPRESSOR_DE,
            self::ELECTROLYZER, self::GATE_CONTROLLER, self::REMOTE_IO,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('hardware.plc_controller_group.' . $v);
        return $translated !== 'hardware.plc_controller_group.' . $v
            ? $translated
            : ucwords(str_replace('_', ' ', $v));
    }

    /**
     * Classify a hardware_devices row to a controller group.
     *
     *   smart_gate_controller                        → gate_controller
     *   compressor_controller + tag A or B           → compressor_ab
     *   compressor_controller + tag D or E           → compressor_de
     *   compressor_controller (other)                → compressor_ab (fallback)
     *   electrolyzer_controller                      → electrolyzer
     *   analyzer + subsystem=analysis                → analysis_unit
     *   plc + subsystem=filling + filling_bay_*      → filling_bay_plc
     *   plc + subsystem=filling                      → main_filling_plc
     *   plc (other)                                  → overall_plc
     *   rio_cabinet                                  → remote_io
     *   safety_device                                → other (covered by §8 boundary note)
     *   everything else                              → other
     */
    public static function deriveFrom(string $deviceType, string $subsystem, ?string $assetTag = null): string
    {
        return match (true) {
            $deviceType === HardwareDeviceType::SMART_GATE_CONTROLLER
                => self::GATE_CONTROLLER,

            $deviceType === HardwareDeviceType::COMPRESSOR_CONTROLLER
                => self::deriveCompressorGroup($assetTag),

            $deviceType === HardwareDeviceType::ELECTROLYZER_CONTROLLER
                => self::ELECTROLYZER,

            $deviceType === HardwareDeviceType::ANALYZER
                && $subsystem === HardwareDeviceSubsystem::ANALYSIS
                => self::ANALYSIS_UNIT,

            $deviceType === HardwareDeviceType::PLC
                && $subsystem === HardwareDeviceSubsystem::FILLING
                => self::MAIN_FILLING_PLC,

            $deviceType === HardwareDeviceType::PLC
                => self::OVERALL_PLC,

            $deviceType === HardwareDeviceType::RIO_CABINET
                => self::REMOTE_IO,

            default => self::OTHER,
        };
    }

    protected static function deriveCompressorGroup(?string $assetTag): string
    {
        if ($assetTag === null) {
            return self::COMPRESSOR_AB;
        }
        $upper = strtoupper($assetTag);
        if (str_contains($upper, '-D') || str_contains($upper, '-E')) {
            return self::COMPRESSOR_DE;
        }
        return self::COMPRESSOR_AB;
    }

    /**
     * Curated signal-group hint per V1.4 §8 "Curated Signal Groups" rule.
     * Used to label the empty signal section until a dedicated
     * plc_signal_samples table lands.
     */
    public static function curatedSignalGroups(string $group): array
    {
        return match ($group) {
            self::OVERALL_PLC, self::MAIN_FILLING_PLC => [
                'overall_plant_state', 'safety_interlocks', 'electrical_utilities',
            ],
            self::FILLING_BAY_PLC => [
                'filling_bay_state', 'safety_interlocks',
            ],
            self::COMPRESSOR_AB, self::COMPRESSOR_DE => [
                'machine_status', 'process_values', 'safety_interlocks',
            ],
            self::ELECTROLYZER => [
                'control_state', 'electrical_utilities', 'safety_interlocks',
            ],
            self::ANALYSIS_UNIT => [
                'process_values', 'overall_plant_state',
            ],
            self::GATE_CONTROLLER => [
                'overall_plant_state',
            ],
            self::REMOTE_IO => [
                'overall_plant_state', 'electrical_utilities',
            ],
            default => [],
        };
    }
}
