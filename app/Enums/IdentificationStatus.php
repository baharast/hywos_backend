<?php

namespace App\Enums;

class IdentificationStatus
{
    public const CHIP_ASSIGNED = 'chip_assigned';
    public const TAN_AVAILABLE = 'tan_available';
    public const MISSING = 'missing';
    public const BLOCKED = 'blocked';
    public const EXPIRED = 'expired';

    public static function label(string $v): string
    {
        $translated = __('driver.identification_status.' . $v);
        return $translated !== 'driver.identification_status.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
