<?php

namespace App\Enums;

/**
 * "Terminal Type" filter from V1.4 §5.
 *
 * DERIVED at read time from (subsystem, device_type, physical_location).
 * NOT stored on `hardware_devices` — the registry classifies devices by
 * subsystem/type/location, and Terminals & Panels collapses those tuples
 * into the 5 operationally-meaningful kinds the FE filter shows.
 *
 * The `other` value catches hardware_devices rows that don't match any
 * of the 5 kinds (e.g. an HMI panel that's not in subsystem=filling).
 * The tab excludes `other` from the default list so the kiosk view
 * stays focused on the V1.4 §5 set.
 */
class TerminalPanelKind
{
    public const ENTRY_GATE = 'entry_gate';
    public const EXIT_GATE = 'exit_gate';
    public const DRIVER_TERMINAL = 'driver_terminal';
    public const FILLING_HMI_PANEL = 'filling_hmi_panel';
    public const OPERATOR_CLIENT = 'operator_client';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::ENTRY_GATE,
            self::EXIT_GATE,
            self::DRIVER_TERMINAL,
            self::FILLING_HMI_PANEL,
            self::OPERATOR_CLIENT,
            self::OTHER,
        ];
    }

    /**
     * The 5 kinds the tab shows by default (excludes OTHER).
     */
    public static function inScopeKinds(): array
    {
        return [
            self::ENTRY_GATE,
            self::EXIT_GATE,
            self::DRIVER_TERMINAL,
            self::FILLING_HMI_PANEL,
            self::OPERATOR_CLIENT,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ENTRY_GATE => 'Entry Gate',
            self::EXIT_GATE => 'Exit Gate',
            self::DRIVER_TERMINAL => 'Driver Terminal',
            self::FILLING_HMI_PANEL => 'Filling HMI Panel',
            self::OPERATOR_CLIENT => 'Operator Client',
            self::OTHER => 'Other',
            default => ucwords(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ENTRY_GATE, self::EXIT_GATE => 'info',
            self::DRIVER_TERMINAL => 'info',
            self::FILLING_HMI_PANEL => 'warning',
            self::OPERATOR_CLIENT => 'neutral',
            self::OTHER => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * Compute the kind from a hardware_devices row.
     *
     * Subsystem is the primary signal (gate / driver_terminal / filling /
     * integration). Device type + physical location refine the classifier
     * for the integration subsystem (operator clients vs. servers).
     */
    public static function deriveFrom(string $subsystem, string $deviceType, string $location): string
    {
        // Gate is split by location — entry vs exit gate panels carry the
        // same subsystem but live at different touchpoints.
        if ($subsystem === HardwareDeviceSubsystem::GATE) {
            if ($location === HardwarePhysicalLocation::ENTRY_GATE) {
                return self::ENTRY_GATE;
            }
            if ($location === HardwarePhysicalLocation::EXIT_GATE) {
                return self::EXIT_GATE;
            }
        }

        if ($subsystem === HardwareDeviceSubsystem::DRIVER_TERMINAL) {
            return self::DRIVER_TERMINAL;
        }

        if ($deviceType === HardwareDeviceType::HMI_PANEL
            && $subsystem === HardwareDeviceSubsystem::FILLING) {
            return self::FILLING_HMI_PANEL;
        }

        if ($deviceType === HardwareDeviceType::SERVER
            && $subsystem === HardwareDeviceSubsystem::INTEGRATION
            && $location === HardwarePhysicalLocation::CONTROL_ROOM) {
            return self::OPERATOR_CLIENT;
        }

        return self::OTHER;
    }
}
