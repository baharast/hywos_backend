<?php

namespace App\Enums;

/**
 * When the sample for an analysis was taken (V1.4 §14 + §17).
 * The frontend must not invent unsupported triggers (e.g. 50%); only
 * these configured values are accepted.
 */
class SamplingTrigger
{
    public const BEFORE_LOADING = 'before_loading';
    public const MAIN_30_PERCENT = 'main_30_percent';
    public const MAIN_60_PERCENT = 'main_60_percent';
    public const MAIN_90_PERCENT = 'main_90_percent';
    public const AFTER_LOADING = 'after_loading';
    public const NOT_APPLICABLE = 'not_applicable';

    public static function all(): array
    {
        return [
            self::BEFORE_LOADING,
            self::MAIN_30_PERCENT,
            self::MAIN_60_PERCENT,
            self::MAIN_90_PERCENT,
            self::AFTER_LOADING,
            self::NOT_APPLICABLE,
        ];
    }

    public static function label(string $v): string
    {
        $translated = __('analysis.sampling_trigger.' . $v);
        return $translated !== 'analysis.sampling_trigger.' . $v ? $translated : $v;
    }
}
