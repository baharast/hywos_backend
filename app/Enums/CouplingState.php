<?php

namespace App\Enums;

class CouplingState
{
    public const COUPLED = 'coupled';
    public const NOT_COUPLED = 'not_coupled';
    public const UNKNOWN = 'unknown';

    public static function all(): array
    {
        return [self::COUPLED, self::NOT_COUPLED, self::UNKNOWN];
    }

    public static function tone(string $value): string
    {
        return match ($value) {
            self::COUPLED => 'info',
            self::NOT_COUPLED => 'neutral',
            self::UNKNOWN => 'offline',
            default => 'neutral',
        };
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::COUPLED => 'Coupled',
            self::NOT_COUPLED => 'Not coupled',
            self::UNKNOWN => 'Unknown',
            default => ucwords(str_replace('_', ' ', $value)),
        };
    }
}
