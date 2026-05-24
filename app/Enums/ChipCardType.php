<?php

namespace App\Enums;

class ChipCardType
{
    public const DRIVER_CARD = 'driver_card';
    public const TRAILER_CHIP_TAG = 'trailer_chip_tag';
    public const SERVICE_TEST = 'service_test';

    public static function all(): array
    {
        return [self::DRIVER_CARD, self::TRAILER_CHIP_TAG, self::SERVICE_TEST];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::DRIVER_CARD => 'Driver card',
            self::TRAILER_CHIP_TAG => 'Trailer chip tag',
            self::SERVICE_TEST => 'Service / test card',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }
}
