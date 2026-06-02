<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Enums\LoadingStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Selected Loading Details — the inline panel opened below the Active
 * Loadings table when a row is clicked. Implements V3.2 §7 shape:
 * header, "What is happening now?", context snapshot, bay/device context,
 * analysis summary, action path and reference links.
 *
 * Lighter than {@see LoadingDetailResource} (which is the full deep-review
 * page §8). The two MUST stay distinct so the FE can keep the selected
 * panel compact without re-fetching the heavier full-detail payload.
 */
class SelectedLoadingDetailsResource extends JsonResource
{
    public function toArray($request): array
    {
        $loadingWire = LoadingStatus::mapToWire((string) $this->loading_status);

        $stationCode = null;
        $stationName = null;
        if ($this->relationLoaded('bayLine') && $this->bayLine) {
            $stationCode = $this->bayLine->code;
            $stationName = $this->bayLine->name;
        }

        $driverName = null;
        $driverId = $this->driver_id;
        if ($this->relationLoaded('driver') && $this->driver) {
            $driverName = trim(
                ($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? '')
            ) ?: null;
        }

        return [
            'header' => [
                'loadingId' => $this->id,
                'loadingNo' => $this->display_no,
                'station' => [
                    'id' => $this->bay_line_id,
                    'name' => $stationName,
                    'code' => $stationCode,
                ],
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
                'issueBadge' => $this->deriveIssueBadge($loadingWire),
            ],

            'whatIsHappeningNow' => $this->deriveWhatIsHappeningNow($loadingWire, $stationName),

            'context' => [
                'order' => [
                    'id' => $this->order_id,
                    'orderNo' => $this->order_no,
                    'sapReference' => $this->sap_order_no,
                ],
                'driver' => $driverId
                    ? ['id' => $driverId, 'name' => $driverName]
                    : null,
                'trailer' => [
                    'id' => $this->trailer_id,
                    'label' => $this->trailer_label,
                ],
                'tractorPlate' => $this->tractor_plate,
                'plantVisit' => $this->plant_visit_id
                    ? ['id' => $this->plant_visit_id, 'visitNo' => $this->visit_no]
                    : null,
                'productQuality' => $this->product_quality,
                'targetQuantity' => $this->target_quantity !== null
                    ? (float) $this->target_quantity : null,
                'actualQuantity' => $this->actual_quantity !== null
                    ? (float) $this->actual_quantity : null,
                'unit' => $this->unit,
                'progressPercent' => $this->progress,
            ],

            'bayDevice' => [
                'bayLineId' => $this->bay_line_id,
                'plcStatus' => $this->plc_status,
                'lastEventAt' => $this->last_event_at?->toIso8601String(),
                'criticalAlarmCount' => (int) $this->critical_alarm_count,
                'alarmCount' => (int) $this->alarm_count,
                'faultReason' => $loadingWire === LoadingStatus::FAULT_DEVICE_ISSUE
                    ? ($this->release_reason ?? __('loading.fault_reason_fallback'))
                    : null,
            ],

            'analysisSummary' => [
                'state' => $this->analysis_status,
                'decisionOwner' => __('loading.analysis_summary.decision_owner'),
                'note' => __('loading.analysis_summary.note'),
            ],

            'documentsReadiness' => $this->deriveDocumentsReadiness($loadingWire),

            'blocker' => $this->deriveBlocker($loadingWire),

            'actionPath' => $this->deriveActionPath($loadingWire),

            'referenceLinks' => $this->deriveReferenceLinks(),

            'correlationId' => $this->correlation_id,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Short attention chip for the header — null when nothing is wrong.
     */
    protected function deriveIssueBadge(?string $loadingWire): ?array
    {
        $issue = match ($loadingWire) {
            LoadingStatus::CLARIFICATION_REQUIRED => ['value' => 'clarification', 'label' => __('loading.issue_badge.clarification'), 'tone' => 'orange'],
            LoadingStatus::QUALITY_BLOCKED => ['value' => 'quality_blocked', 'label' => __('loading.issue_badge.quality_blocked'), 'tone' => 'danger'],
            LoadingStatus::FAULT_DEVICE_ISSUE => ['value' => 'fault', 'label' => __('loading.issue_badge.fault'), 'tone' => 'danger'],
            LoadingStatus::WAITING_PRE_ANALYSIS,
            LoadingStatus::WAITING_MAIN_ANALYSIS,
            LoadingStatus::PAUSED_WAITING => ['value' => 'waiting', 'label' => __('loading.issue_badge.waiting'), 'tone' => 'warning'],
            default => null,
        };

        if (! $issue && (bool) $this->has_clarification) {
            return ['value' => 'clarification', 'label' => __('loading.issue_badge.clarification'), 'tone' => 'orange'];
        }
        if (! $issue && (int) $this->critical_alarm_count > 0) {
            return ['value' => 'critical_alarm', 'label' => __('loading.issue_badge.critical_alarm'), 'tone' => 'danger'];
        }
        return $issue;
    }

    /**
     * One plain-language sentence answering V3.2 §7 "What is happening now?".
     */
    protected function deriveWhatIsHappeningNow(?string $loadingWire, ?string $stationName): string
    {
        $where = $stationName ?: 'the bay';
        $key = $loadingWire ?? 'default';
        $translated = __('loading.what_happening.' . $key, ['where' => $where]);

        return $translated !== 'loading.what_happening.' . $key
            ? $translated
            : __('loading.what_happening.default', ['where' => $where]);
    }

    /**
     * Document readiness block — only meaningful once loading has completed
     * (or is post-loading). Returns null otherwise to keep the panel compact.
     */
    protected function deriveDocumentsReadiness(?string $loadingWire): ?array
    {
        if (! in_array(
            $loadingWire,
            [LoadingStatus::COMPLETED, LoadingStatus::DOCUMENTS_PENDING, LoadingStatus::QUALITY_BLOCKED],
            true
        )) {
            return null;
        }
        return [
            'isReady' => $loadingWire === LoadingStatus::COMPLETED,
            'note' => $loadingWire === LoadingStatus::DOCUMENTS_PENDING
                ? __('loading.documents.pending')
                : ($loadingWire === LoadingStatus::QUALITY_BLOCKED
                    ? __('loading.documents.blocked')
                    : __('loading.documents.available')),
        ];
    }

    protected function deriveBlocker(?string $loadingWire): ?array
    {
        if (! in_array(
            $loadingWire,
            [
                LoadingStatus::CLARIFICATION_REQUIRED,
                LoadingStatus::QUALITY_BLOCKED,
                LoadingStatus::FAULT_DEVICE_ISSUE,
                LoadingStatus::PAUSED_WAITING,
            ],
            true
        ) && ! (bool) $this->has_clarification) {
            return null;
        }

        return [
            'reason' => $this->release_reason,
            'reasonCode' => $this->release_reason_code,
            'clarificationCaseId' => $this->clarification_case_id ?? null,
        ];
    }

    /**
     * V3.2 §7.1 action path rules — one primary owning path. The selected
     * panel surfaces only this one action; reference links handle the rest.
     */
    protected function deriveActionPath(?string $loadingWire): array
    {
        $value = match (true) {
            $loadingWire === LoadingStatus::CLARIFICATION_REQUIRED
                || (bool) $this->has_clarification => 'open_clarification',
            $loadingWire === LoadingStatus::QUALITY_BLOCKED
                || $loadingWire === LoadingStatus::WAITING_PRE_ANALYSIS
                || $loadingWire === LoadingStatus::WAITING_MAIN_ANALYSIS => 'open_analysis',
            $loadingWire === LoadingStatus::FAULT_DEVICE_ISSUE
                || $this->plc_status === 'failed'
                || (int) $this->critical_alarm_count > 0 => 'open_device',
            $loadingWire === LoadingStatus::DOCUMENTS_PENDING => 'open_documents',
            ! empty($this->plant_visit_id) => 'open_visit',
            default => 'none',
        };

        return [
            'value' => $value,
            'label' => __('loading.action_path.' . $value),
        ];
    }

    /**
     * Secondary deeplinks: loading order, plant visit, event journal, audit
     * trail. Only emit links whose target exists.
     */
    protected function deriveReferenceLinks(): array
    {
        $links = [];

        if ($this->order_id) {
            $links[] = [
                'kind' => 'loading_order',
                'label' => __('loading.reference_link.loading_order'),
                'path' => "/operations/loading-orders/{$this->order_id}",
            ];
        }
        if ($this->plant_visit_id) {
            $links[] = [
                'kind' => 'plant_visit',
                'label' => __('loading.reference_link.plant_visit'),
                'path' => "/operations/active-plant-visits/{$this->plant_visit_id}",
            ];
        }

        // Event / audit are always available (per-loading sub-routes).
        $links[] = [
            'kind' => 'event_journal',
            'label' => __('loading.reference_link.event_journal'),
            'path' => "/api/loading-control/loadings/{$this->id}/events",
        ];
        $links[] = [
            'kind' => 'audit_trail',
            'label' => __('loading.reference_link.audit_trail'),
            'path' => "/api/loading-control/loadings/{$this->id}/audit",
        ];

        return $links;
    }
}
