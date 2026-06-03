<?php

namespace App\Enums;

class TechnicalSuitabilityState
{
    public const APPROVED = 'approved';
    public const INCOMPLETE = 'incomplete';
    public const NOT_SUITABLE = 'not_suitable';
    public const NEEDS_REVIEW = 'needs_review';

    public static function all(): array
    {
        return [self::APPROVED, self::INCOMPLETE, self::NOT_SUITABLE, self::NEEDS_REVIEW];
    }

    public static function label(string $v): string
    {
        $translated = __('vehicles.technical_suitability.' . $v);
        return $translated !== 'vehicles.technical_suitability.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::APPROVED => 'success',
            self::INCOMPLETE => 'warning',
            self::NEEDS_REVIEW => 'orange',
            self::NOT_SUITABLE => 'danger',
            default => 'neutral',
        };
    }
}
