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
        $translated = __('masterdata.export_job_status.' . $value);
        return $translated !== 'masterdata.export_job_status.' . $value ? $translated : ucfirst(str_replace('_', ' ', $value));
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
