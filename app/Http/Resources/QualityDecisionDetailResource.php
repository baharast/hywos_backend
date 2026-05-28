<?php

namespace App\Http\Resources;

use App\Enums\AnalysisElementStatus;
use App\Enums\ActiveAnalysisType;
use App\Enums\AnalysisUserAction;
use App\Enums\CertificateImpact;
use App\Enums\ClosedDecisionStatus;
use App\Enums\OpenDecisionStatus;
use App\Enums\ResultStatus;
use App\Enums\GasComponent;
use App\Services\QualityDecision\QualityDecisionService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Detail payload for the V1.1 §11 Selected Result Details panel.
 *
 * Includes the always-six-row element comparison, a decision record
 * reconstructed from the analysis row + recent audit_logs, and the
 * downstream-impact block (V1.1 §11.6).
 *
 * The caller passes recent audit rows via `additional(['auditRows' => ...])`
 * because the resource isn't allowed to issue extra queries.
 */
class QualityDecisionDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $svc = app(QualityDecisionService::class);

        $latestAttempt = $this->latestAttempt;
        $elements = $latestAttempt
            ? collect($this->elementResults)->where('attempt_id', $latestAttempt->id)->values()
            : collect();

        $resultStatus = $svc->deriveResultStatus($elements);
        [$decisionStatus, $isOpen] = $svc->deriveDecisionStatus($this->resource);
        $certificateImpact = $svc->deriveCertificateImpact($this->resource, $resultStatus, $decisionStatus);

        $decisionLabel = $isOpen
            ? OpenDecisionStatus::label($decisionStatus)
            : ClosedDecisionStatus::label($decisionStatus);
        $decisionTone = $isOpen
            ? OpenDecisionStatus::tone($decisionStatus)
            : ClosedDecisionStatus::tone($decisionStatus);

        $lastAction = $svc->inferLastDecisionAction($this->resource);

        return [
            'id' => $this->id,
            'displayNo' => $this->display_no,

            /* ---------- Result summary (§11.1) ---------- */
            'resultSummary' => [
                'analysisType' => [
                    'value' => $this->analysis_type,
                    'label' => ActiveAnalysisType::label($this->analysis_type ?? ''),
                ],
                'samplingTrigger' => $this->sampling_trigger,
                'resultStatus' => [
                    'value' => $resultStatus,
                    'label' => ResultStatus::label($resultStatus),
                    'tone' => ResultStatus::tone($resultStatus),
                ],
                'decisionStatus' => [
                    'value' => $decisionStatus,
                    'label' => $decisionLabel,
                    'tone' => $decisionTone,
                    'isOpen' => $isOpen,
                ],
                'specVersion' => $this->spec_version,
                'productCode' => $this->product_code,
                'maxAttempts' => $this->max_attempts,
                'attemptCount' => $this->attempt_count,
            ],

            /* ---------- Context snapshot (§11.2) ---------- */
            'context' => [
                'order' => [
                    'id' => $this->order_id,
                    'no' => $this->order_no,
                    'sapOrderNo' => $this->sap_order_no,
                ],
                'plantVisit' => [
                    'id' => $this->plant_visit_id,
                    'no' => $this->visit_no,
                ],
                'driver' => [
                    'id' => $this->driver_id,
                    'name' => $this->driver_name,
                ],
                'trailer' => [
                    'id' => $this->trailer_id,
                    'label' => $this->trailer_label,
                ],
                'tractor' => [
                    'id' => $this->tractor_id,
                    'label' => $this->tractor_label,
                ],
                'station' => [
                    'bayLineId' => $this->bay_line_id,
                    'name' => $this->station_name,
                ],
                'device' => [
                    'id' => $this->device_id,
                    'bmk' => $this->device_bmk,
                    'name' => $this->device_name,
                ],
            ],

            /* ---------- 6-element comparison (§11.3, always 6 rows) ---------- */
            'elements' => $this->buildSixElementComparison($elements),

            /* ---------- Decision record (§11.5) ---------- */
            'decisionRecord' => [
                'status' => [
                    'value' => $decisionStatus,
                    'label' => $decisionLabel,
                    'tone' => $decisionTone,
                ],
                'decisionTime' => $this->closed_at?->toIso8601String()
                    ?? $this->cancelled_at?->toIso8601String(),
                'decisionMakerId' => $this->closed_by_user_id ?? $this->cancelled_by_user_id,
                'justification' => $this->cancellation_reason ?? $this->hold_reason,
                'lastAction' => $lastAction === null ? null : [
                    'value' => $lastAction,
                    'label' => AnalysisUserAction::label($lastAction),
                ],
                'isOverride' => $lastAction === AnalysisUserAction::MANUAL_FUNCTIONAL_APPROVAL,
                'activeActionLocation' => $isOpen ? '/analysis-quality/active-analyses' : null,
                'auditRows' => $this->additional['auditRows'] ?? [],
            ],

            /* ---------- Downstream impact (§11.6) ---------- */
            'downstreamImpact' => [
                'loadingContinuation' => $this->resolveLoadingContinuation($decisionStatus),
                'certificate' => [
                    'value' => $certificateImpact,
                    'label' => CertificateImpact::label($certificateImpact),
                    'tone' => CertificateImpact::tone($certificateImpact),
                ],
                'documentReadiness' => $this->relatedDocumentReadiness($certificateImpact),
                'orderTrailerState' => $this->resolveOrderTrailerState($decisionStatus),
                'clarificationCaseId' => null,
            ],

            /* ---------- Reference links (§7 / §11.7) ---------- */
            'referenceLinks' => [
                'activeAnalysis' => $isOpen
                    ? "/analysis-quality/active-analyses?analysisId={$this->id}"
                    : null,
                'loadingOrder' => $this->order_id
                    ? "/operations/loading-orders/{$this->order_id}"
                    : null,
                'plantVisit' => $this->plant_visit_id
                    ? "/operations/active-plant-visits/{$this->plant_visit_id}"
                    : null,
                'documents' => $this->order_id
                    ? "/documents-reports/operational-documents?orderId={$this->order_id}"
                    : null,
                'analysisDevice' => $this->device_id
                    ? "/analysis-quality/analysis-devices/{$this->device_id}"
                    : null,
                'eventJournal' => "/alarms-events/event-journal?entity=analysis&entityId={$this->id}",
                'auditTrail' => "/alarms-events/audit-trail?entity=analysis&entityId={$this->id}",
            ],

            'closedAt' => $this->closed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * V1.1 §11.3: every panel must show all 6 elements. Missing ones are
     * filled with a synthetic row whose status='missing' so the FE can
     * still render the row with a clear reason.
     */
    protected function buildSixElementComparison($actualElements): array
    {
        $byElement = collect($actualElements)->keyBy('element');
        $rows = [];
        foreach (GasComponent::all() as $code) {
            $row = $byElement->get($code);
            if ($row) {
                $rows[] = $this->serialiseElementRow($row);
            } else {
                $rows[] = [
                    'element' => $code,
                    'elementLabel' => GasComponent::label($code),
                    'measuredValue' => null,
                    'unit' => null,
                    'lowerLimit' => null,
                    'upperLimit' => null,
                    'limitLabel' => null,
                    'differenceLabel' => null,
                    'status' => [
                        'value' => AnalysisElementStatus::MISSING,
                        'label' => AnalysisElementStatus::label(AnalysisElementStatus::MISSING),
                        'tone' => AnalysisElementStatus::tone(AnalysisElementStatus::MISSING),
                    ],
                    'validityReason' => 'No element row recorded for this analysis.',
                    'measuredAt' => null,
                ];
            }
        }
        return $rows;
    }

    protected function serialiseElementRow($row): array
    {
        return [
            'element' => $row->element,
            'elementLabel' => GasComponent::label($row->element),
            'measuredValue' => $row->measured_value === null ? null : (float) $row->measured_value,
            'unit' => $row->unit,
            'lowerLimit' => $row->lower_limit === null ? null : (float) $row->lower_limit,
            'upperLimit' => $row->upper_limit === null ? null : (float) $row->upper_limit,
            'limitLabel' => $row->limit_label,
            'differenceLabel' => $row->difference_label,
            'status' => [
                'value' => $row->status,
                'label' => AnalysisElementStatus::label($row->status),
                'tone' => AnalysisElementStatus::tone($row->status),
            ],
            'validityReason' => $row->validity_reason,
            'measuredAt' => $row->measured_at?->toIso8601String(),
        ];
    }

    protected function resolveLoadingContinuation(string $decisionStatus): string
    {
        return match ($decisionStatus) {
            ClosedDecisionStatus::APPROVED, ClosedDecisionStatus::RELEASED => 'allowed',
            ClosedDecisionStatus::BLOCKED, ClosedDecisionStatus::REJECTED => 'blocked',
            ClosedDecisionStatus::CLOSED => 'completed',
            default => 'pending',
        };
    }

    protected function resolveOrderTrailerState(string $decisionStatus): string
    {
        return match ($decisionStatus) {
            ClosedDecisionStatus::REJECTED => 'rejected',
            ClosedDecisionStatus::BLOCKED => 'blocked',
            ClosedDecisionStatus::APPROVED, ClosedDecisionStatus::RELEASED => 'released',
            default => 'awaiting_action',
        };
    }

    protected function relatedDocumentReadiness(string $certificateImpact): string
    {
        return match ($certificateImpact) {
            CertificateImpact::GENERATED => 'generated',
            CertificateImpact::ALLOWED => 'allowed',
            CertificateImpact::BLOCKED => 'blocked',
            CertificateImpact::NOT_REQUIRED => 'not_required',
            default => 'pending',
        };
    }
}
