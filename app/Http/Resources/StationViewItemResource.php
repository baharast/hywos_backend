<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Enums\BayLineStatus;
use App\Enums\LoadingStatus;
use App\Models\BayLine;
use App\Models\LoadingOperation;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Station-View bay-line card. Implements the `BayLineCard` shape from
 * FillTrack Loading Control UX Spec V3.2 §11.1.
 *
 * The resource wraps a BayLine + the optional active LoadingOperation:
 *   [
 *     'bay'    => BayLine,
 *     'active' => LoadingOperation|null,
 *   ]
 *
 * Card display priority and the 7-value `status` come from
 * {@see BayLineStatus::derive()}. `actionPath` and `issueReason` derivation
 * are local because they depend on which V3.2 §7.1 ownership route applies.
 */
class StationViewItemResource extends JsonResource
{
    /** Sessions older than this are considered stale (V3.2 §9 freshness rule). */
    protected const STALE_THRESHOLD_SECONDS = 90;

    public function toArray($request): array
    {
        /** @var BayLine $bay */
        $bay = $this->resource['bay'];
        /** @var LoadingOperation|null $active */
        $active = $this->resource['active'] ?? null;

        $bayStatus = BayLineStatus::derive($bay, $active);
        $loadingWire = $active
            ? LoadingStatus::mapToWire((string) $active->loading_status)
            : null;

        $driverName = null;
        if ($active && $active->relationLoaded('driver') && $active->driver) {
            $driverName = trim(
                ($active->driver->first_name ?? '') . ' ' . ($active->driver->last_name ?? '')
            ) ?: null;
        }

        $lastUpdatedAt = $active?->last_event_at
            ?? $active?->updated_at
            ?? $bay->updated_at;

        return [
            'id' => $bay->id,
            'bayLineNumber' => $this->extractBayNumber($bay->code),
            'displayName' => $bay->name,
            'code' => $bay->code,

            'status' => [
                'value' => $bayStatus,
                'label' => BayLineStatus::label($bayStatus),
                'tone' => BayLineStatus::tone($bayStatus),
            ],

            'activeLoadingId' => $active?->id,
            'orderNo' => $active?->order_no,
            'sapReference' => $active?->sap_order_no,

            'driverName' => $driverName,
            'trailerLabel' => $active?->trailer_label,
            'tractorPlate' => $active?->tractor_plate,

            'targetQuantity' => $active?->target_quantity !== null
                ? (float) $active->target_quantity
                : null,
            'actualQuantity' => $active?->actual_quantity !== null
                ? (float) $active->actual_quantity
                : null,
            'unit' => $active?->unit,
            'progressPercent' => $active?->progress,

            'loadingState' => $loadingWire
                ? [
                    'value' => $loadingWire,
                    'label' => LoadingStatus::label($loadingWire),
                    'tone' => LoadingStatus::tone($loadingWire),
                ]
                : null,

            'analysisState' => $active?->analysis_status
                ? [
                    'value' => $active->analysis_status,
                    'label' => AnalysisStatus::label($active->analysis_status),
                    'tone' => AnalysisStatus::tone($active->analysis_status),
                ]
                : null,

            'plcStatus' => $active?->plc_status,
            'hasClarification' => (bool) ($active?->has_clarification ?? false),
            'clarificationCaseId' => $active?->clarification_case_id ?? null,

            'issueReason' => $this->deriveIssueReason($active, $bayStatus, $loadingWire),
            'actionPath' => $this->deriveActionPath($bay, $active, $bayStatus, $loadingWire),

            'lastUpdatedAt' => $lastUpdatedAt?->toIso8601String(),
            'isStale' => $this->isStale($lastUpdatedAt),

            // ── V3.2 station-card extensions (2026-06-01) ──────────────────
            // Additive only — no existing field is touched. Wire keys are
            // snake_case per the V3.2 brief (existing fields stay camelCase
            // for backward compatibility). Every new field is nullable so
            // the response stays valid before PLC / maintenance / interlocks
            // integrations land.
            //
            // Sources today:
            //   capability_bar : parsed from `bay_lines.pressure_class`
            //                    (best-effort numeric extraction)
            //   customer_id    : `loading_operations.customer_id`
            //   customer_name  : `loading_operations.customer_name`
            //   plc_id         : `bay_lines.related_device_id` (PLC) →
            //                    fall back to `related_panel_id`
            //
            // Always-null until upstream lands:
            //   live_pressure  : PLC telemetry (no column / gateway yet)
            //   temperature    : PLC telemetry (no column / gateway yet)
            //   analysis_required : bay capability flag (no column yet —
            //                    `loading_operations.analysis_status` is
            //                    a state, not a per-bay/product requirement)
            //   target_pressure: per-loading setpoint (no column yet)
            //   safety         : PLC interlock summary array (no source yet)
            //   maintenance    : maintenance-record summary (no table yet)
            //   process_step   : FE falls back to loadingState.label
            'live_pressure' => null,
            'temperature' => null,
            'capability_bar' => $this->parseCapabilityBar($bay->pressure_class),
            'analysis_required' => null,
            'target_pressure' => null,
            'customer_id' => $active?->customer_id,
            'customer_name' => $active?->customer_name,
            'safety' => null,
            'maintenance' => null,
            'plc_id' => $bay->related_device_id ?? $bay->related_panel_id,
            'process_step' => null,
        ];
    }

    /**
     * Best-effort numeric extraction from the free-text `pressure_class`
     * column (e.g. "150 bar" → 150.0, "200bar" → 200.0, "Class A" → null).
     * Until a typed capability column lands, this lets the FE show a value
     * for bays whose pressure_class already carries the number.
     */
    protected function parseCapabilityBar(?string $pressureClass): ?float
    {
        if (! $pressureClass) {
            return null;
        }
        if (preg_match('/(\d+(?:\.\d+)?)/', $pressureClass, $m)) {
            return (float) $m[1];
        }
        return null;
    }

    /**
     * Extract the bay number from the code (e.g. "BAY-3" → 3). Returns null
     * when the code carries no usable digit run. The wire schema (§11.1) types
     * this as `1 | 2 | 3 | 4 | 5 | 6` but the column is free-text so we
     * tolerate non-numeric codes and emit null.
     */
    protected function extractBayNumber(?string $code): ?int
    {
        if (! $code) {
            return null;
        }
        if (preg_match('/(\d+)/', $code, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    /**
     * Human-readable issue. Backend-supplied where available, otherwise
     * derived from the highest-priority blocking condition so the operator
     * never sees an empty card with a `fault_blocked` chip and no reason.
     */
    protected function deriveIssueReason(
        ?LoadingOperation $active,
        string $bayStatus,
        ?string $loadingWire
    ): ?string {
        if (! $active) {
            return null;
        }

        // Prefer the persisted release_reason — it's the dispatcher's text.
        if (! empty($active->release_reason)) {
            return $active->release_reason;
        }

        // Fallback derivations for V3.2 §5.3 fault_blocked cases — every
        // fault chip must explain WHY (V3.2 §6.5 waiting-rule applied
        // symmetrically: no fault without reason).
        if ($bayStatus === BayLineStatus::FAULT_BLOCKED) {
            return match ($loadingWire) {
                LoadingStatus::CLARIFICATION_REQUIRED => 'Clarification required — see open case.',
                LoadingStatus::QUALITY_BLOCKED => 'Quality decision blocks continuation.',
                LoadingStatus::FAULT_DEVICE_ISSUE => 'Station / device fault.',
                default => (int) $active->critical_alarm_count > 0
                    ? 'Critical alarm active on station.'
                    : null,
            };
        }

        if ($bayStatus === BayLineStatus::WAITING_ANALYSIS) {
            return $loadingWire === LoadingStatus::WAITING_PRE_ANALYSIS
                ? 'Waiting for pre-analysis result.'
                : 'Waiting for main-analysis result.';
        }

        if ($bayStatus === BayLineStatus::COMPLETED_WAITING_DOCUMENTS) {
            return 'Loading complete — documents pending.';
        }

        return null;
    }

    /**
     * Map the card to its single primary action path per V3.2 §7.1. Returns
     * one of: none | open_clarification | open_analysis | open_device |
     * open_documents | open_visit.
     */
    protected function deriveActionPath(
        BayLine $bay,
        ?LoadingOperation $active,
        string $bayStatus,
        ?string $loadingWire
    ): string {
        if (! $active) {
            return 'none';
        }

        // V3.2 §7.1 — wrong trailer / wrong order / wrong bay → clarification.
        if ($loadingWire === LoadingStatus::CLARIFICATION_REQUIRED
            || (bool) $active->has_clarification
        ) {
            return 'open_clarification';
        }

        // Quality blocked or analysis-waiting → Analysis & Quality module.
        if ($loadingWire === LoadingStatus::QUALITY_BLOCKED
            || $bayStatus === BayLineStatus::WAITING_ANALYSIS
        ) {
            return 'open_analysis';
        }

        // Device / PLC fault → Device Detail.
        if ($loadingWire === LoadingStatus::FAULT_DEVICE_ISSUE
            || $active->plc_status === 'failed'
            || (int) $active->critical_alarm_count > 0
        ) {
            return 'open_device';
        }

        // Loading completed + waiting documents → Documents & Reports.
        if ($bayStatus === BayLineStatus::COMPLETED_WAITING_DOCUMENTS
            || $loadingWire === LoadingStatus::DOCUMENTS_PENDING
        ) {
            return 'open_documents';
        }

        // Active loading bound to a plant visit → drilling into visit context
        // is the natural follow-up. Falls back to `none` when no visit yet.
        if (! empty($active->plant_visit_id)) {
            return 'open_visit';
        }

        return 'none';
    }

    protected function isStale(?Carbon $lastUpdatedAt): bool
    {
        if (! $lastUpdatedAt) {
            return false;
        }
        return $lastUpdatedAt->diffInSeconds(now()) > self::STALE_THRESHOLD_SECONDS;
    }
}
