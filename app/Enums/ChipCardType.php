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
        $translated = __('vehicles.chip_type.' . $v);
        return $translated !== 'vehicles.chip_type.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
