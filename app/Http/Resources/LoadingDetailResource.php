<?php

namespace App\Http\Resources;

use App\Enums\AnalysisStatus;
use App\Enums\LoadingStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full Loading Detail page — the deep-review shape per FillTrack Loading
 * Control UX Spec V3.2 §8. Returned by `GET /api/loading-control/loadings/{id}`
 * for the dedicated detail route `/operations/loading-control/loadings/:id`.
 *
 * Heavier than {@see SelectedLoadingDetailsResource} (the inline §7 panel).
 * Layout of the `tabs` block mirrors §8: Overview, Bay & Device, Analysis,
 * Clarification / Blockers, Documents, Events & Audit.
 *
 * Note: events / audit tabs are populated by separate paged endpoints
 * (`/loadings/{id}/events`, `/loadings/{id}/audit`). We surface them as
 * `null` here so the FE knows the slot exists but must fetch separately.
 */
class LoadingDetailResource extends JsonResource
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
        $driverPhone = null;
        $driverLanguage = null;
        if ($this->relationLoaded('driver') && $this->driver) {
            $driverName = trim(
                ($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? '')
            ) ?: null;
            $driverPhone = $this->driver->phone ?? null;
            $driverLanguage = $this->driver->preferred_culture_code ?? null;
        }

        return [
            /* -----------------------------------------------------
             * Header — identity + chips. Mirrors the §8 detail header.
             * -----------------------------------------------------
             */
            'id' => $this->id,
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

            /* -----------------------------------------------------
             * Progress block — used by every tab header.
             * -----------------------------------------------------
             */
            'progress' => [
                'target' => $this->target_quantity !== null ? (float) $this->target_quantity : null,
                'actual' => $this->actual_quantity !== null ? (float) $this->actual_quantity : null,
                'percent' => $this->progress,
                'unit' => $this->unit,
            ],

            /* -----------------------------------------------------
             * Assignment snapshot — driver / tractor / trailer /
             * plant visit / customer.
             * -----------------------------------------------------
             */
            'assignment' => [
                'driver' => $this->driver_id
                    ? [
                        'id' => $this->driver_id,
                        'fullName' => $driverName,
                        'phone' => $driverPhone,
                        'language' => $driverLanguage,
                    ]
                    : null,
                'tractor' => ['plate' => $this->tractor_plate],
                'trailer' => [
                    'id' => $this->trailer_id,
                    'label' => $this->trailer_label,
                ],
                'plantVisit' => $this->plant_visit_id
                    ? ['id' => $this->plant_visit_id, 'visitNo' => $this->visit_no]
                    : null,
                'customer' => $this->customer_id
                    ? ['id' => $this->customer_id, 'name' => $this->customer_name]
                    : null,
            ],

            'productQuality' => $this->product_quality,
            'release' => [
                'source' => $this->release_source,
                'reasonCode' => $this->release_reason_code,
                'reason' => $this->release_reason,
            ],
            'plcStatus' => $this->plc_status,
            'lastEventAt' => $this->last_event_at?->toIso8601String(),
            'correlationId' => $this->correlation_id,
            'notes' => $this->notes,
            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),

            'nextStep' => $this->deriveNextStep($loadingWire),

            /* -----------------------------------------------------
             * V3.2 §8 tab payloads.
             *
             * Events & Audit fetched separately via per-loading
             * sub-routes; we surface them here as `null` so the FE
             * knows the slot exists but must paginate via:
             *   GET /loadings/{id}/events
             *   GET /loadings/{id}/audit
             * -----------------------------------------------------
             */
            'tabs' => [
                'overview' => [
                    'whatIsHappeningNow' => $this->deriveWhatIsHappeningNow($loadingWire, $stationName),
                    'nextStep' => $this->deriveNextStep($loadingWire),
                ],

                'bayDevice' => [
                    'bayLineId' => $this->bay_line_id,
                    'stationName' => $stationName,
                    'plcStatus' => $this->plc_status,
                    'lastEventAt' => $this->last_event_at?->toIso8601String(),
                    'criticalAlarmCount' => (int) $this->critical_alarm_count,
                    'alarmCount' => (int) $this->alarm_count,
                    'note' => 'Device / PLC diagnostics live in System & Devices.',
                ],

                'analysis' => [
                    'currentState' => $this->analysis_status
                        ? [
                            'value' => $this->analysis_status,
                            'label' => AnalysisStatus::label($this->analysis_status),
                            'tone' => AnalysisStatus::tone($this->analysis_status),
                        ]
                        : null,
                    'decisionOwner' => 'Analysis & Quality',
                    'note' => 'Read-only view. Decisions are made in Analysis & Quality.',
                ],

                'clarificationBlockers' => [
                    'hasOpenClarification' => (bool) $this->has_clarification,
                    'clarificationCaseId' => $this->clarification_case_id ?? null,
                    'currentBlocker' => $this->deriveBlocker($loadingWire),
                ],

                'documents' => [
                    'isRelevant' => in_array(
                        $loadingWire,
                        [
                            LoadingStatus::COMPLETED,
                            LoadingStatus::DOCUMENTS_PENDING,
                            LoadingStatus::QUALITY_BLOCKED,
                        ],
                        true
                    ),
                    'note' => 'Document print / reprint / handover lives in Documents & Reports.',
                    'items' => [],
                ],

                'events' => null,
                'audit' => null,
            ],
        ];
    }

    protected function deriveWhatIsHappeningNow(?string $loadingWire, ?string $stationName): string
    {
        $where = $stationName ?: 'the bay';
        return match ($loadingWire) {
            LoadingStatus::ASSIGNED_READY_FOR_BAY => "Loading is assigned to {$where}; awaiting pre-analysis trigger.",
            LoadingStatus::WAITING_PRE_ANALYSIS => "Waiting for pre-analysis result before release at {$where}.",
            LoadingStatus::READY_FOR_LOADING => "Released for loading at {$where}; awaiting start at the panel.",
            LoadingStatus::LOADING => "Loading is in progress at {$where}.",
            LoadingStatus::PAUSED_WAITING => "Loading at {$where} is paused — operator review required.",
            LoadingStatus::COMPLETED => "Physical loading complete at {$where}.",
            LoadingStatus::WAITING_MAIN_ANALYSIS => "Waiting for main-analysis result after loading at {$where}.",
            LoadingStatus::QUALITY_BLOCKED => "Quality decision blocks documents/exit for the loading at {$where}.",
            LoadingStatus::DOCUMENTS_PENDING => "Loading complete at {$where} — documents not yet ready.",
            LoadingStatus::CLARIFICATION_REQUIRED => "Loading at {$where} is held — clarification case open.",
            LoadingStatus::FAULT_DEVICE_ISSUE => "Station / device fault affecting the loading at {$where}.",
            default => "Loading at {$where} is in an unknown state — investigate.",
        };
    }

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
        ];
    }
}
