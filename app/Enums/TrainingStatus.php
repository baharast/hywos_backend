<?php

namespace App\Enums;

class TrainingStatus
{
    public const VALID = 'valid';
    public const EXPIRED = 'expired';
    public const MISSING = 'missing';
    public const NOT_REQUIRED = 'not_required';
    public const UNKNOWN = 'unknown';

    public static function label(string $v): string
    {
        $translated = __('driver.training_status.' . $v);
        return $translated !== 'driver.training_status.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }
}
