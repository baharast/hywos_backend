<?php

namespace App\Http\Resources;

use App\Enums\ActiveAnalysisType;
use App\Enums\ClosedDecisionStatus;
use App\Enums\OpenDecisionStatus;
use App\Enums\ResultStatus;
use App\Enums\CertificateImpact;
use App\Services\QualityDecision\QualityDecisionService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Row shape for the Results & Quality Decisions list (V1.1 §10).
 *
 * The resource composes derived `resultStatus`, `decisionStatus` and
 * `certificateImpact` from the underlying `ActiveAnalysis` row + its
 * latest attempt's element results — none of these are stored columns.
 *
 * `actionOwner` is the V1.1 §4 rule: closed rows are reviewed here,
 * open rows route the user back to Active Analyses.
 */
class QualityDecisionListItemResource extends JsonResource
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

        $decisionTone = $isOpen
            ? OpenDecisionStatus::tone($decisionStatus)
            : ClosedDecisionStatus::tone($decisionStatus);
        $decisionLabel = $isOpen
            ? OpenDecisionStatus::label($decisionStatus)
            : ClosedDecisionStatus::label($decisionStatus);

        return [
            'id' => $this->id,
            'displayNo' => $this->display_no,
            'analysisId' => $this->id,
            'analysisType' => [
                'value' => $this->analysis_type,
                'label' => ActiveAnalysisType::label($this->analysis_type ?? ''),
            ],
            'order' => [
                'id' => $this->order_id,
                'no' => $this->order_no,
                'sapOrderNo' => $this->sap_order_no,
            ],
            'trailer' => [
                'id' => $this->trailer_id,
                'label' => $this->trailer_label,
            ],
            'product' => [
                'specId' => $this->product_spec_id,
                'code' => $this->product_code,
                'specVersion' => $this->spec_version,
            ],
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
            'elementSummary' => $svc->elementSummary($elements),
            'failedParameters' => $svc->failedParameters($elements),
            'certificateImpact' => [
                'value' => $certificateImpact,
                'label' => CertificateImpact::label($certificateImpact),
                'tone' => CertificateImpact::tone($certificateImpact),
            ],
            'actionOwner' => $isOpen ? 'active_analyses' : 'results',
            'station' => [
                'bayLineId' => $this->bay_line_id,
                'name' => $this->station_name,
            ],
            'closedAt' => $this->closed_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
