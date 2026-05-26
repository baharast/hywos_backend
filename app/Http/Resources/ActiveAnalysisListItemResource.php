<?php

namespace App\Http\Resources;

use App\Enums\ActiveAnalysisStatus;
use App\Enums\ActiveAnalysisType;
use App\Enums\AnalysisUserAction;
use App\Enums\SamplingTrigger;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Main-table row for the Active Analyses queue (V1.4 §10).
 *
 * Excludes full element values — those appear only in the workbench
 * (V1.4 §18). `elementSummary` is a cached short string ("6/6 valid",
 * "O2 high, CO2 invalid") computed by the service.
 */
class ActiveAnalysisListItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status;
        $type = $this->analysis_type;
        $trigger = $this->sampling_trigger;
        $action = $this->required_action;

        return [
            'id' => $this->id,
            'displayNo' => $this->display_no,

            'analysisType' => [
                'value' => $type,
                'label' => ActiveAnalysisType::label($type),
            ],
            'samplingTrigger' => [
                'value' => $trigger,
                'label' => SamplingTrigger::label($trigger),
            ],
            'status' => [
                'value' => $status,
                'label' => ActiveAnalysisStatus::label($status),
                'tone' => ActiveAnalysisStatus::tone($status),
            ],

            'orderNo' => $this->order_no,
            'sapOrderNo' => $this->sap_order_no,
            'trailerLabel' => $this->trailer_label,
            'stationName' => $this->station_name,
            'deviceName' => $this->device_name,

            'attemptCount' => (int) $this->attempt_count,
            'maxAttempts' => (int) $this->max_attempts,

            'elementSummary' => $this->element_summary,

            'requiredAction' => $action === null ? null : [
                'value' => $action,
                'label' => AnalysisUserAction::label($action),
                'ruleSource' => AnalysisUserAction::ruleSource($action),
            ],
            'requiredActionReason' => $this->required_action_reason,
            'latestMessage' => $this->latest_message,

            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
