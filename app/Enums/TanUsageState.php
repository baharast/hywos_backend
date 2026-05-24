<?php

namespace App\Enums;

class TanUsageState
{
    public const UNUSED = 'unused';
    public const CONSUMED = 'consumed';
    public const FAILED = 'failed';
    public const BLOCKED = 'blocked';
    public const NOT_APPLICABLE = 'not_applicable';

    public static function all(): array
    {
        return [self::UNUSED, self::CONSUMED, self::FAILED, self::BLOCKED, self::NOT_APPLICABLE];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::UNUSED => 'Unused',
            self::CONSUMED => 'Consumed',
            self::FAILED => 'Failed',
            self::BLOCKED => 'Blocked',
            self::NOT_APPLICABLE => 'Not applicable',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::UNUSED => 'info',
            self::CONSUMED => 'success',
            self::FAILED => 'danger',
            self::BLOCKED => 'danger',
            self::NOT_APPLICABLE => 'neutral',
            default => 'neutral',
        };
    }
}
