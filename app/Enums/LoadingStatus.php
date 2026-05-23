<?php

namespace App\Enums;

class LoadingStatus
{
    public const ASSIGNED = 'assigned';
    public const WAITING_PRE_ANALYSIS = 'waiting_pre_analysis';
    public const RELEASED = 'released';
    public const LOADING = 'loading';
    public const PAUSED = 'paused';
    public const COMPLETED = 'completed';
    public const WAITING_MAIN_ANALYSIS = 'waiting_main_analysis';
    public const QUALITY_CHECK_OPEN = 'quality_check_open';
    public const QUALITY_BLOCKED = 'quality_blocked';
    public const CLARIFICATION_REQUIRED = 'clarification_required';
    public const FAILED = 'failed';
    public const CANCELLED = 'cancelled';

    public static function all(): array
    {
        return [
            self::ASSIGNED, self::WAITING_PRE_ANALYSIS, self::RELEASED, self::LOADING,
            self::PAUSED, self::COMPLETED, self::WAITING_MAIN_ANALYSIS,
            self::QUALITY_CHECK_OPEN, self::QUALITY_BLOCKED, self::CLARIFICATION_REQUIRED,
            self::FAILED, self::CANCELLED,
        ];
    }

    public static function terminal(): array
    {
        return [self::COMPLETED, self::FAILED, self::CANCELLED];
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::COMPLETED, self::RELEASED => 'success',
            self::ASSIGNED, self::LOADING => 'info',
            self::WAITING_PRE_ANALYSIS, self::WAITING_MAIN_ANALYSIS,
            self::QUALITY_CHECK_OPEN, self::PAUSED => 'warning',
            self::CLARIFICATION_REQUIRED => 'orange',
            self::QUALITY_BLOCKED, self::FAILED => 'danger',
            self::CANCELLED => 'neutral',
            default => 'neutral',
        };
    }

    public static function label(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
}
