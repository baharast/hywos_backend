<?php

namespace App\Enums;

class ExportCategory
{
    public const DRIVERS = 'drivers';
    public const TRAILERS = 'trailers';
    public const TRACTORS_VEHICLES = 'tractors_vehicles';
    public const CUSTOMERS = 'customers';
    public const FREIGHT_FORWARDERS_CARRIERS = 'freight_forwarders_carriers';
    public const CHIP_CARDS = 'chip_cards';
    public const TANS = 'tans';

    public static function all(): array
    {
        return [
            self::DRIVERS,
            self::TRAILERS,
            self::TRACTORS_VEHICLES,
            self::CUSTOMERS,
            self::FREIGHT_FORWARDERS_CARRIERS,
            self::CHIP_CARDS,
            self::TANS,
        ];
    }

    public static function label(string $value): string
    {
        $translated = __('masterdata.export_category.' . $value);
        return $translated !== 'masterdata.export_category.' . $value ? $translated : ucfirst(str_replace('_', ' ', $value));
    }
}
