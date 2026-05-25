<?php

namespace App\Enums;

class ClarificationSeverity
{
    public const LOW = 'low';
    public const NORMAL = 'normal';
    public const HIGH = 'high';
    public const CRITICAL = 'critical';

    public static function all(): array
    {
        return [self::LOW, self::NORMAL, self::HIGH, self::CRITICAL];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::LOW => 'Low',
            self::NORMAL => 'Normal',
            self::HIGH => 'High',
            self::CRITICAL => 'Critical',
            default => ucfirst($v),
        };
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::LOW => 'neutral',
            self::NORMAL => 'info',
            self::HIGH => 'warning',
            self::CRITICAL => 'danger',
            default => 'neutral',
        };
    }
}
