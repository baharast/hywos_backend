<?php

namespace App\Enums;

class DriverStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const BLOCKED = 'blocked';
    public const UNKNOWN = 'unknown';

    public static function label(string $v): string
    {
        $translated = __('driver.status.' . $v);
        return $translated !== 'driver.status.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
