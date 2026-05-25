<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Enums\LoadingStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Active-Loadings row. Implements the `ActiveLoadingListItem` shape from
 * FillTrack Loading Control UX Spec V3.2 §11.1.
 *
 * `loading_status` is stored in the DB using legacy values
 * (`assigned`, `released`, `paused`, `failed`, `cancelled`,
 * `quality_check_open`) that predate V3.2. Always translate via
 * {@see LoadingStatus::mapToWire()} before emitting.
 */
class ActiveLoadingListItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $bayLineNumber = null;
        $stationCode = null;
        $stationName = null;
        if ($this->relationLoaded('bayLine') && $this->bayLine) {
            $stationCode = $this->bayLine->code;
            $stationName = $this->bayLine->name;
            if (preg_match('/(\d+)/', (string) $this->bayLine->code, $m)) {
                $bayLineNumber = (int) $m[1];
            }
        }

        $driverName = null;
        if ($this->relationLoaded('driver') && $this->driver) {
            $driverName = trim(
                ($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? '')
            ) ?: null;
        }

        $loadingWire = LoadingStatus::mapToWire((string) $this->loading_status);
        $issueReason = $this->deriveIssueReason($loadingWire);
        $nextStep = $this->deriveNextStep($loadingWire);

        return [
            'id' => $this->id,
            'loadingNo' => $this->display_no,
            'bayLineNumber' => $bayLineNumber,
            'station' => [
                'id' => $this->bay_line_id,
                'name' => $stationName,
                'code' => $stationCode,
            ],

            'orderId' => $this->order_id,
            'orderNo' => $this->order_no,
            'sapReference' => $this->sap_order_no,

            'plantVisitId' => $this->plant_visit_id,
            'visitNo' => $this->visit_no,

            'driverId' => $this->driver_id,
            'driverName' => $driverName,

            'trailerId' => $this->trailer_id,
            'trailerLabel' => $this->trailer_label,
            'tractorPlate' => $this->tractor_plate,

            'productQuality' => $this->product_quality,
            'targetQuantity' => $this->target_quantity !== null
                ? (float) $this->target_quantity : null,
            'actualQuantity' => $this->actual_quantity !== null
                ? (float) $this->actual_quantity : null,
            'unit' => $this->unit,
            'progressPercent' => $this->progress,

            'loadingState' => $loadingWire
                ? [
                    'value' => $loadingWire,
                    'label' => LoadingStatus::label($loadingWire),
                    'tone' => LoadingStatus::tone($loadingWire),
                ]
                : null,

            'analysisState' => $this->analysis_status
                ? [
                    'value' => $this->analysis_status,
                    'label' => AnalysisStatus::label($this->analysis_status),
                    'tone' => AnalysisStatus::tone($this->analysis_status),
                ]
                : null,

            'issueReason' => $issueReason,
            'nextStep' => $nextStep,

            'hasClarification' => (bool) $this->has_clarification,
            'clarificationCaseId' => $this->clarification_case_id ?? null,

            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Issue / Reason column (V3.2 §6.2). Show ONLY when there is a real
     * problem or waiting state — the rest of the time it is a quiet dash on
     * the FE side (we emit null, FE renders "–"). Per V3.2 §6.5 waiting
     * rule, waiting states always carry their reason.
     */
    protected function deriveIssueReason(?string $loadingWire): ?string
    {
        if (! empty($this->release_reason)) {
            return $this->release_reason;
        }

        return match ($loadingWire) {
            LoadingStatus::CLARIFICATION_REQUIRED => 'Clarification required — see open case.',
            LoadingStatus::QUALITY_BLOCKED => 'Quality decision blocks continuation.',
            LoadingStatus::FAULT_DEVICE_ISSUE => 'Station / device fault.',
            LoadingStatus::WAITING_PRE_ANALYSIS => 'Waiting for pre-analysis result.',
            LoadingStatus::WAITING_MAIN_ANALYSIS => 'Waiting for main-analysis result.',
            LoadingStatus::PAUSED_WAITING => 'Loading paused — operator review required.',
            LoadingStatus::DOCUMENTS_PENDING => 'Loading complete — documents pending.',
            default => (int) $this->critical_alarm_count > 0
                ? 'Critical alarm active on station.'
                : null,
        };
    }

    /**
     * Action-oriented next step per V3.2 §6.2 "Issue / Next Step" column.
     * Mirrors §6.5 owning-module suggestions.
     */
    protected function deriveNextStep(?string $loadingWire): ?string
    {
        return match ($loadingWire) {
            LoadingStatus::ASSIGNED_READY_FOR_BAY => 'Awaiting pre-analysis trigger.',
            LoadingStatus::WAITING_PRE_ANALYSIS => 'Pre-analysis in progress — open Active Analyses.',
            LoadingStatus::READY_FOR_LOADING => 'Awaiting loading start at panel.',
            LoadingStatus::LOADING => 'Monitor loading progress.',
            LoadingStatus::PAUSED_WAITING => 'Resolve pause reason on panel.',
            LoadingStatus::WAITING_MAIN_ANALYSIS => 'Awaiting main-analysis — open Active Analyses.',
            LoadingStatus::QUALITY_BLOCKED => 'Escalate to Analysis & Quality.',
            LoadingStatus::DOCUMENTS_PENDING => 'Open Documents & Reports.',
            LoadingStatus::CLARIFICATION_REQUIRED => 'Open Clarification Case.',
            LoadingStatus::FAULT_DEVICE_ISSUE => 'Open Device Detail / Active Alarms.',
            LoadingStatus::COMPLETED => 'Loading completed.',
            default => null,
        };
    }
}
