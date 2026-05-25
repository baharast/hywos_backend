<?php

namespace App\Enums;

class LoadingOrderSource
{
    public const SAP = 'sap';
    public const MANUAL = 'manual';

    public static function all(): array
    {
        return [self::SAP, self::MANUAL];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::SAP => 'SAP',
            self::MANUAL => 'Manual',
            default => ucfirst($v),
        };
    }
}
