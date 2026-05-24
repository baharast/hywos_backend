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
        return match ($value) {
            self::DRIVERS => 'Drivers',
            self::TRAILERS => 'Trailers',
            self::TRACTORS_VEHICLES => 'Tractors / Vehicles',
            self::CUSTOMERS => 'Customers',
            self::FREIGHT_FORWARDERS_CARRIERS => 'Freight Forwarders / Carriers',
            self::CHIP_CARDS => 'Chip Cards',
            self::TANS => 'TANs',
            default => $value,
        };
    }
}
