<?php

namespace App\Enums;

class ChipUsageResult
{
    public const ACCEPTED = 'accepted';
    public const REJECTED = 'rejected';
    public const UNKNOWN = 'unknown';
    public const BLOCKED = 'blocked';
    public const EXPIRED = 'expired';
    public const MISMATCH = 'mismatch';
    public const FAILED = 'failed';

    public static function all(): array
    {
        return [
            self::ACCEPTED, self::REJECTED, self::UNKNOWN,
            self::BLOCKED, self::EXPIRED, self::MISMATCH, self::FAILED,
        ];
    }

    public static function failureValues(): array
    {
        return [self::REJECTED, self::BLOCKED, self::EXPIRED, self::MISMATCH, self::FAILED];
    }

    public static function label(string $v): string
    {
        $translated = __('vehicles.chip_usage_result.' . $v);
        return $translated !== 'vehicles.chip_usage_result.' . $v
            ? $translated
            : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::ACCEPTED => 'success',
            self::UNKNOWN => 'neutral',
            self::REJECTED, self::BLOCKED, self::FAILED => 'danger',
            self::EXPIRED, self::MISMATCH => 'orange',
            default => 'neutral',
        };
    }
}
