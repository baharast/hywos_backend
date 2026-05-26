<?php

namespace App\Enums;

/**
 * The 9 MVP report identifiers exposed by /api/documents-reports/reports.
 *
 * Values are kebab-case to match the URL path style (`/reports/{reportId}`).
 * The spec §14 lists snake_case ("daily_operations", "order_execution", …)
 * for the FE-side TypeScript type; the controller accepts both forms via
 * normaliseId() to keep the FE migration story painless.
 */
class ReportId
{
    public const DAILY_OPERATIONS = 'daily-operations';
    public const LOADING_HISTORY = 'loading-history';
    public const QUANTITY_THROUGHPUT = 'quantity-throughput';
    public const STATION_UTILIZATION = 'station-utilization';
    public const ANALYSIS_QUALITY = 'analysis-quality';
    public const DOCUMENTS_PRINT = 'documents-print';
    public const CLARIFICATIONS_ALARMS_AUDIT = 'clarifications-alarms-audit';
    public const GATE_ACCESS_EXIT = 'gate-access-exit';
    public const DEVICE_HEALTH = 'device-health';

    public static function all(): array
    {
        return [
            self::DAILY_OPERATIONS,
            self::LOADING_HISTORY,
            self::QUANTITY_THROUGHPUT,
            self::STATION_UTILIZATION,
            self::ANALYSIS_QUALITY,
            self::DOCUMENTS_PRINT,
            self::CLARIFICATIONS_ALARMS_AUDIT,
            self::GATE_ACCESS_EXIT,
            self::DEVICE_HEALTH,
        ];
    }

    /**
     * Accept both kebab-case (URL style) and snake_case (spec §14 TS style).
     * Returns the canonical kebab-case constant or null when unknown.
     */
    public static function normaliseId(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $kebab = str_replace('_', '-', strtolower($raw));
        // Spec §14 alias: order_execution → loading-history
        if ($kebab === 'order-execution') {
            $kebab = self::LOADING_HISTORY;
        }
        return in_array($kebab, self::all(), true) ? $kebab : null;
    }
}
