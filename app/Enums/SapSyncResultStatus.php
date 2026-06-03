<?php

namespace App\Enums;

/**
 * Whether a single SAP import/export record succeeded, is queued, failed, or
 * has been resolved after prior failure (V1.5 §9.2).
 */
class SapSyncResultStatus
{
    public const SUCCESS = 'success';
    public const PENDING = 'pending';
    public const FAILED = 'failed';
    public const RESOLVED = 'resolved';

    public static function all(): array
    {
        return [
            self::SUCCESS,
            self::PENDING,
            self::FAILED,
            self::RESOLVED,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('masterdata.sap_result_status.' . $v);
        return $translated !== 'masterdata.sap_result_status.' . $v ? $translated : ucfirst(str_replace('_', ' ', $v));
    }

    public static function tone(string $v): string
    {
        return match ($v) {
            self::SUCCESS => 'success',
            self::PENDING => 'info',
            self::FAILED => 'danger',
            self::RESOLVED => 'success',
            default => 'neutral',
        };
    }
}
