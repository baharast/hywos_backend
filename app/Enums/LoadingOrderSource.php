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
        $translated = __('loading.order_source.' . $v);
        return $translated !== 'loading.order_source.' . $v ? $translated : ucfirst($v);
    }
}
