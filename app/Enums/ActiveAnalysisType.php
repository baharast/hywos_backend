<?php

namespace App\Enums;

/**
 * Type of an active analysis job. Per FillTrack Active Analyses V1.4 §14
 * + §17.
 *
 *   pre_analysis   — sample taken before loading; gates loading release
 *   main_analysis  — sample during/after loading; gates documents + exit
 *   final_analysis — final summary analysis when configured
 *   retest         — re-run after invalid / failed result
 */
class ActiveAnalysisType
{
    public const PRE_ANALYSIS = 'pre_analysis';
    public const MAIN_ANALYSIS = 'main_analysis';
    public const FINAL_ANALYSIS = 'final_analysis';
    public const RETEST = 'retest';

    public static function all(): array
    {
        return [
            self::PRE_ANALYSIS,
            self::MAIN_ANALYSIS,
            self::FINAL_ANALYSIS,
            self::RETEST,
        ];
    }

    public static function label(string $v): string
    {
        return match ($v) {
            self::PRE_ANALYSIS => 'Pre-analysis',
            self::MAIN_ANALYSIS => 'Main analysis',
            self::FINAL_ANALYSIS => 'Final analysis',
            self::RETEST => 'Retest',
            default => ucfirst(str_replace('_', ' ', $v)),
        };
    }
}
