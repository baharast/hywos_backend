<?php

namespace App\Enums;

/**
 * DERIVED reader kind for the V1.4 §7 Card Readers / Trailer-Chip
 * Readers internal tab.
 *
 * NOT a stored column — composed at read time from (device_type,
 * subsystem, physical_location). The spec lists 5 concrete reader
 * roles (covered objects table in §7); we add OTHER for anything that
 * shouldn't show up on this tab.
 *
 * Don't conflate Reader Device Type with Reader Kind:
 *   - device_type is hardware-facing (rfid_reader / trailer_chip_reader)
 *   - kind is BUSINESS role (entry-gate personal reader, exit ID reader,
 *     driver-terminal reader, filling-bay reader, trailer registration
 *     reader)
 */
class ReaderKind
{
    public const ENTRY_GATE_PERSONAL = 'entry_gate_personal';
    public const EXIT_ID_READER = 'exit_id_reader';
    public const DRIVER_TERMINAL_READER = 'driver_terminal_reader';
    public const FILLING_BAY_READER = 'filling_bay_reader';
    public const TRAILER_REGISTRATION_READER = 'trailer_registration_reader';
    public const OTHER = 'other';

    public static function all(): array
    {
        return [
            self::ENTRY_GATE_PERSONAL,
            self::EXIT_ID_READER,
            self::DRIVER_TERMINAL_READER,
            self::FILLING_BAY_READER,
            self::TRAILER_REGISTRATION_READER,
            self::OTHER,
        ];
    }

    /**
     * The 5 in-scope kinds. OTHER is excluded from the default tab list
     * and from FE filter dropdowns.
     */
    public static function inScopeKinds(): array
    {
        return [
            self::ENTRY_GATE_PERSONAL,
            self::EXIT_ID_READER,
            self::DRIVER_TERMINAL_READER,
            self::FILLING_BAY_READER,
            self::TRAILER_REGISTRATION_READER,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::ENTRY_GATE_PERSONAL => 'Entry Gate Personal RFID Reader',
            self::EXIT_ID_READER => 'Exit Identification Reader',
            self::DRIVER_TERMINAL_READER => 'Driver Terminal RFID/Chip Reader',
            self::FILLING_BAY_READER => 'Filling Bay RFID Reader',
            self::TRAILER_REGISTRATION_READER => 'Trailer-Chip Reader (Registration)',
            self::OTHER => 'Other Reader',
            default => ucwords(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::TRAILER_REGISTRATION_READER => 'info',
            default => 'neutral',
        };
    }

    /**
     * Classify a hardware_devices row to a ReaderKind.
     *
     * Rules:
     *   - trailer_chip_reader device → TRAILER_REGISTRATION_READER
     *   - rfid_reader + entry_gate location → ENTRY_GATE_PERSONAL
     *   - rfid_reader + exit_gate location → EXIT_ID_READER
     *   - rfid_reader + driver_terminal location → DRIVER_TERMINAL_READER
     *   - rfid_reader + filling_bay_NN location → FILLING_BAY_READER
     *   - everything else → OTHER
     */
    public static function deriveFrom(string $deviceType, string $subsystem, string $location): string
    {
        if ($deviceType === HardwareDeviceType::TRAILER_CHIP_READER) {
            return self::TRAILER_REGISTRATION_READER;
        }
        if ($deviceType === HardwareDeviceType::RFID_READER) {
            return match ($location) {
                HardwarePhysicalLocation::ENTRY_GATE => self::ENTRY_GATE_PERSONAL,
                HardwarePhysicalLocation::EXIT_GATE => self::EXIT_ID_READER,
                HardwarePhysicalLocation::DRIVER_TERMINAL => self::DRIVER_TERMINAL_READER,
                HardwarePhysicalLocation::FILLING_BAY_01,
                HardwarePhysicalLocation::FILLING_BAY_02,
                HardwarePhysicalLocation::FILLING_BAY_03,
                HardwarePhysicalLocation::FILLING_BAY_04,
                HardwarePhysicalLocation::FILLING_BAY_05,
                HardwarePhysicalLocation::FILLING_BAY_06 => self::FILLING_BAY_READER,
                default => self::OTHER,
            };
        }
        return self::OTHER;
    }

    /**
     * V1.4 §7 "Linked Process" column: the operational flow the reader
     * participates in. Derived from the reader kind.
     */
    public static function linkedProcess(string $kind): string
    {
        return match ($kind) {
            self::ENTRY_GATE_PERSONAL => 'entry',
            self::EXIT_ID_READER => 'exit',
            self::DRIVER_TERMINAL_READER => 'terminal_login',
            self::FILLING_BAY_READER => 'loading_release',
            self::TRAILER_REGISTRATION_READER => 'trailer_registration',
            default => 'unknown',
        };
    }

    public static function linkedProcessLabel(string $kind): string
    {
        return match (self::linkedProcess($kind)) {
            'entry' => 'Entry',
            'exit' => 'Exit',
            'terminal_login' => 'Terminal login',
            'loading_release' => 'Loading release',
            'trailer_registration' => 'Trailer registration',
            default => 'Unknown',
        };
    }
}
