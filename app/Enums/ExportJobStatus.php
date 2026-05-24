<?php

namespace App\Enums;

class ExportJobStatus
{
    public const QUEUED = 'queued';
    public const GENERATING = 'generating';
    public const READY = 'ready';
    public const FAILED = 'failed';
    public const EXPIRED = 'expired';

    public static function all(): array
    {
        return [self::QUEUED, self::GENERATING, self::READY, self::FAILED, self::EXPIRED];
    }

    public static function label(string $value): string
    {
        return match ($value) {
            self::QUEUED => 'Queued',
            self::GENERATING => 'Generating',
            self::READY => 'Ready',
            self::FAILED => 'Failed',
            self::EXPIRED => 'Expired',
            default => $value,
        };
    }

    public static function tone(string $value): string
    {
        return match ($value) {
            self::QUEUED => 'info',
            self::GENERATING => 'warning',
            self::READY => 'success',
            self::FAILED => 'danger',
            self::EXPIRED => 'offline',
            default => 'neutral',
        };
    }
}
