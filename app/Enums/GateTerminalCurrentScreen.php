<?php

namespace App\Enums;

/**
 * Current screen the driver is on at a touchpoint. V2.3 §10.
 *
 * "Denied" and "Support" are NOT screens — they are session states (V2.3 §10
 * footnote).
 */
class GateTerminalCurrentScreen
{
    public const DRIVER_LOGIN = 'driver_login';
    public const TRAILER_IDENTIFICATION = 'trailer_identification';
    public const TRACTOR_MACHINE_PLATE = 'tractor_machine_plate';
    public const TASK_SELECTION = 'task_selection';
    public const INSTRUCTION = 'instruction';
    public const DOCUMENTS = 'documents';
    public const EXIT_VALIDATION = 'exit_validation';

    public static function all(): array
    {
        return [
            self::DRIVER_LOGIN,
            self::TRAILER_IDENTIFICATION,
            self::TRACTOR_MACHINE_PLATE,
            self::TASK_SELECTION,
            self::INSTRUCTION,
            self::DOCUMENTS,
            self::EXIT_VALIDATION,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::DRIVER_LOGIN => 'Driver Login',
            self::TRAILER_IDENTIFICATION => 'Trailer Identification',
            self::TRACTOR_MACHINE_PLATE => 'Tractor / Machine Plate',
            self::TASK_SELECTION => 'Task Selection',
            self::INSTRUCTION => 'Instruction',
            self::DOCUMENTS => 'Documents',
            self::EXIT_VALIDATION => 'Exit Validation',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }
}
