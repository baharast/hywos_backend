<?php

namespace App\Enums;

class AnalysisStatus
{
    public const NOT_STARTED = 'not_started';
    public const PRE_ANALYSIS_OPEN = 'pre_analysis_open';
    public const PRE_ANALYSIS_OK = 'pre_analysis_ok';
    public const PRE_ANALYSIS_FAILED = 'pre_analysis_failed';
    public const MAIN_ANALYSIS_OPEN = 'main_analysis_open';
    public const MAIN_ANALYSIS_OK = 'main_analysis_ok';
    public const FUNCTIONALLY_NOK = 'functionally_nok';
    public const TECHNICALLY_INVALID = 'technically_invalid';
    public const QUALITY_CHECKED = 'quality_checked';

    public static function all(): array
    {
        return [
            self::NOT_STARTED, self::PRE_ANALYSIS_OPEN, self::PRE_ANALYSIS_OK,
            self::PRE_ANALYSIS_FAILED, self::MAIN_ANALYSIS_OPEN, self::MAIN_ANALYSIS_OK,
            self::FUNCTIONALLY_NOK, self::TECHNICALLY_INVALID, self::QUALITY_CHECKED,
        ];
    }

    public static function tone(string $status): string
    {
        return match ($status) {
            self::PRE_ANALYSIS_OK, self::MAIN_ANALYSIS_OK, self::QUALITY_CHECKED => 'success',
            self::PRE_ANALYSIS_OPEN, self::MAIN_ANALYSIS_OPEN => 'warning',
            self::PRE_ANALYSIS_FAILED, self::FUNCTIONALLY_NOK => 'danger',
            self::TECHNICALLY_INVALID => 'orange',
            self::NOT_STARTED => 'offline',
            default => 'neutral',
        };
    }

    public static function label(string $status): string
    {
        $translated = __('analysis.status.' . $status);
        return $translated !== 'analysis.status.' . $status
            ? $translated
            : ucfirst(str_replace('_', ' ', $status));
    }
}
