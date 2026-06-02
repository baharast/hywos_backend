<?php

namespace App\Enums;

use App\Models\BayLine;
use App\Models\LoadingOperation;

/**
 * Bay Line operational status — the 7-state model from FillTrack Loading
 * Control UX Spec V3.2 §5.3 + §11.1.
 *
 * Distinct from {@see LoadingStatus} which describes the *process* state of
 * a single loading operation. A bay line summarises whatever loading (if
 * any) is bound to it plus the bay's own device/maintenance state.
 *
 * Replaces the legacy {@see \App\Enums\StationStatus} (free/reserved/occupied/
 * loading/fault/maintenance/offline). The new vocabulary collapses
 * fault+blocked+clarification into one `fault_blocked` chip per V3.2 §5.3.
 */
class BayLineStatus
{
    public const AVAILABLE = 'available';
    public const RESERVED = 'reserved';
    public const LOADING = 'loading';
    public const WAITING_ANALYSIS = 'waiting_analysis';
    public const COMPLETED_WAITING_DOCUMENTS = 'completed_waiting_documents';
    public const FAULT_BLOCKED = 'fault_blocked';
    public const MAINTENANCE_OFFLINE = 'maintenance_offline';

    public static function all(): array
    {
        return [
            self::AVAILABLE,
            self::RESERVED,
            self::LOADING,
            self::WAITING_ANALYSIS,
            self::COMPLETED_WAITING_DOCUMENTS,
            self::FAULT_BLOCKED,
            self::MAINTENANCE_OFFLINE,
        ];
    }

    public static function label(string $value): string
    {
        $translated = __('loading.bay_status.' . $value);
        return $translated !== 'loading.bay_status.' . $value
            ? $translated
            : ucfirst(str_replace('_', ' ', $value));
    }

    public static function tone(string $value): string
    {
        return match ($value) {
            self::AVAILABLE => 'success',
            self::RESERVED => 'info',
            self::LOADING => 'info',
            self::WAITING_ANALYSIS => 'warning',
            self::COMPLETED_WAITING_DOCUMENTS => 'success',
            self::FAULT_BLOCKED => 'danger',
            self::MAINTENANCE_OFFLINE => 'maintenance',
            default => 'neutral',
        };
    }

    /**
     * Derive the bay's display status applying V3.2 §5.2 card display priority:
     *
     *   1. Fault / Offline / Critical device issue
     *   2. Clarification required / wrong assignment
     *   3. Quality / analysis blocked
     *   4. Loading
     *   5. Completed / waiting documents
     *   6. Reserved / assigned
     *   7. Available
     *
     * Per spec the card must show the highest-priority CURRENT operational
     * state, never the most recent historical event.
     */
    public static function derive(BayLine $bay, ?LoadingOperation $active): string
    {
        $bayCode = strtolower((string) ($bay->status_code ?? ''));

        // Priority 1 — bay-level offline/maintenance overrides the loading.
        // The bay is physically unavailable so loading state is irrelevant.
        if (in_array($bayCode, ['maintenance', 'offline'], true)) {
            return self::MAINTENANCE_OFFLINE;
        }

        // Priority 1 (continued) — bay-level fault, or active loading reports
        // a device-issue / failure / quality block / critical alarm. V3.2 §5.3
        // collapses these into a single fault_blocked chip; reason chip shown
        // separately in the card body.
        $loadingWire = $active
            ? LoadingStatus::mapToWire((string) $active->loading_status)
            : null;

        $hasDeviceFault = $bayCode === 'fault'
            || ($active && $active->plc_status === 'failed')
            || ($active && (int) $active->critical_alarm_count > 0);

        if ($hasDeviceFault
            || $loadingWire === LoadingStatus::FAULT_DEVICE_ISSUE
            || $loadingWire === LoadingStatus::QUALITY_BLOCKED
        ) {
            return self::FAULT_BLOCKED;
        }

        // Priority 2 — clarification required (operator must resolve before
        // loading can continue). Per V3.2 §5.3 also folds under fault_blocked
        // visual tone, but downstream actionPath differs.
        if ($loadingWire === LoadingStatus::CLARIFICATION_REQUIRED
            || ($active && (bool) $active->has_clarification)
        ) {
            return self::FAULT_BLOCKED;
        }

        // No active loading → calm available state.
        if (! $active) {
            return self::AVAILABLE;
        }

        // Priority 3 — waiting on analysis (pre or main).
        if (in_array(
            $loadingWire,
            [LoadingStatus::WAITING_PRE_ANALYSIS, LoadingStatus::WAITING_MAIN_ANALYSIS],
            true
        )) {
            return self::WAITING_ANALYSIS;
        }

        // Priority 4 — physical loading in progress.
        if ($loadingWire === LoadingStatus::LOADING) {
            return self::LOADING;
        }

        // Priority 5 — completed; next step is documents/exit.
        if (in_array(
            $loadingWire,
            [LoadingStatus::COMPLETED, LoadingStatus::DOCUMENTS_PENDING],
            true
        )) {
            return self::COMPLETED_WAITING_DOCUMENTS;
        }

        // Priority 6 — reserved/assigned: bay is prepared but not loading yet.
        if (in_array(
            $loadingWire,
            [
                LoadingStatus::ASSIGNED_READY_FOR_BAY,
                LoadingStatus::READY_FOR_LOADING,
                LoadingStatus::PAUSED_WAITING,
            ],
            true
        )) {
            return self::RESERVED;
        }

        // Priority 7 — fallback.
        return self::AVAILABLE;
    }
}
