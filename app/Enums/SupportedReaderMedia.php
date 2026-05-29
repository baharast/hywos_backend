<?php

namespace App\Enums;

/**
 * DERIVED "Supported Media / Use" enum for V1.4 §7.
 *
 * Spec explicitly separates Reader Device Type (hardware-facing) from
 * Supported Media / Use (business). A single rfid_reader hardware row
 * can support multiple media depending on its kind:
 *   - entry-gate / exit-gate / driver-terminal readers → driver chip + TAN
 *   - filling-bay reader → driver chip + RFID authorization
 *   - trailer-chip reader → trailer chip
 *
 * Computed from ReaderKind at read time.
 */
class SupportedReaderMedia
{
    public const DRIVER_CHIP = 'driver_chip';
    public const TAN_INPUT = 'tan_input';
    public const TRAILER_CHIP = 'trailer_chip';
    public const RFID_AUTHORIZATION = 'rfid_authorization';

    public static function all(): array
    {
        return [
            self::DRIVER_CHIP,
            self::TAN_INPUT,
            self::TRAILER_CHIP,
            self::RFID_AUTHORIZATION,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::DRIVER_CHIP => 'Driver chip',
            self::TAN_INPUT => 'TAN input',
            self::TRAILER_CHIP => 'Trailer chip',
            self::RFID_AUTHORIZATION => 'RFID authorization',
            default => ucwords(str_replace('_', ' ', $v)),
        };
    }

    /**
     * Returns the set of media supported by this reader kind.
     *
     * @return array<int,string>
     */
    public static function forKind(string $kind): array
    {
        return match ($kind) {
            ReaderKind::ENTRY_GATE_PERSONAL,
            ReaderKind::EXIT_ID_READER,
            ReaderKind::DRIVER_TERMINAL_READER => [
                self::DRIVER_CHIP,
                self::TAN_INPUT,
            ],
            ReaderKind::FILLING_BAY_READER => [
                self::DRIVER_CHIP,
                self::RFID_AUTHORIZATION,
            ],
            ReaderKind::TRAILER_REGISTRATION_READER => [
                self::TRAILER_CHIP,
            ],
            default => [],
        };
    }
}
