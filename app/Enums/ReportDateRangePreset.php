<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/**
 * Date-range presets accepted by `?range_preset=` on every report endpoint.
 *
 * `custom` requires `range_from` + `range_to` query parameters; the other
 * presets resolve to a server-side [from, to] window in `resolve()` so the
 * timezone is the server's, not the browser's — keeps audit-safe.
 */
class ReportDateRangePreset
{
    public const TODAY = 'today';
    public const YESTERDAY = 'yesterday';
    public const THIS_WEEK = 'this_week';
    public const LAST_WEEK = 'last_week';
    public const THIS_MONTH = 'this_month';
    public const LAST_MONTH = 'last_month';
    public const LAST_7_DAYS = 'last_7_days';
    public const LAST_30_DAYS = 'last_30_days';
    public const CUSTOM = 'custom';

    public static function all(): array
    {
        return [
            self::TODAY, self::YESTERDAY, self::THIS_WEEK, self::LAST_WEEK,
            self::THIS_MONTH, self::LAST_MONTH, self::LAST_7_DAYS,
            self::LAST_30_DAYS, self::CUSTOM,
        ];
    }

    /**
     * Resolve a preset (and optional explicit from/to for custom) to a
     * concrete `[from, to]` pair using inclusive `from`, exclusive `to`.
     *
     * Falls back to `today` when an unknown preset is supplied so the
     * controller never has to handle a "bad preset" 422 — the spec wants
     * reports to render even when the FE sends junk filters.
     */
    public static function resolve(
        ?string $preset,
        ?string $customFrom = null,
        ?string $customTo = null
    ): array {
        $now = CarbonImmutable::now();
        $preset = $preset ?: self::TODAY;

        switch ($preset) {
            case self::CUSTOM:
                $from = $customFrom ? CarbonImmutable::parse($customFrom) : $now->startOfDay();
                $to = $customTo ? CarbonImmutable::parse($customTo) : $now->endOfDay();
                break;
            case self::YESTERDAY:
                $from = $now->subDay()->startOfDay();
                $to = $now->subDay()->endOfDay();
                break;
            case self::THIS_WEEK:
                $from = $now->startOfWeek();
                $to = $now->endOfWeek();
                break;
            case self::LAST_WEEK:
                $from = $now->subWeek()->startOfWeek();
                $to = $now->subWeek()->endOfWeek();
                break;
            case self::THIS_MONTH:
                $from = $now->startOfMonth();
                $to = $now->endOfMonth();
                break;
            case self::LAST_MONTH:
                $from = $now->subMonth()->startOfMonth();
                $to = $now->subMonth()->endOfMonth();
                break;
            case self::LAST_7_DAYS:
                $from = $now->subDays(6)->startOfDay();
                $to = $now->endOfDay();
                break;
            case self::LAST_30_DAYS:
                $from = $now->subDays(29)->startOfDay();
                $to = $now->endOfDay();
                break;
            case self::TODAY:
            default:
                $preset = self::TODAY;
                $from = $now->startOfDay();
                $to = $now->endOfDay();
        }

        return [
            'preset' => $preset,
            'from' => $from,
            'to' => $to,
        ];
    }
}
