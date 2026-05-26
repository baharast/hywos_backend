<?php

namespace App\Http\Resources;

use App\Enums\ActiveAnalysisStatus;
use App\Enums\ActiveAnalysisType;
use App\Enums\AnalysisUserAction;
use App\Enums\GasComponent;
use App\Enums\SamplingTrigger;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Selected Analysis Decision Workbench payload (V1.4 §11).
 *
 * Carries the full context snapshot, the 6-element comparison table,
 * the attempt timeline, and the action panel (one primary action with
 * its reason + rule source + reasonRequired flag).
 */
class ActiveAnalysisDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status;
        $type = $this->analysis_type;
        $trigger = $this->sampling_trigger;
        $action = $this->required_action;
        $allowed = is_array($this->allowed_actions) ? $this->allowed_actions : [];

        // Sort element results by canonical gas display order so the
        // table always renders H2 → O2 → N2 → CH4 → CO → CO2.
        $elements = $this->elementResults
            ->sortBy(fn ($e) => GasComponent::displayOrder($e->element))
            ->values();

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

            // Context snapshot (V1.4 §11.1)
            'context' => [
                'order' => [
                    'id' => $this->order_id,
                    'orderNo' => $this->order_no,
                    'sapOrderNo' => $this->sap_order_no,
                ],
                'plantVisit' => [
                    'id' => $this->plant_visit_id,
                    'visitNo' => $this->visit_no,
                ],
                'loadingOperationId' => $this->loading_operation_id,
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
                'specification' => [
                    'id' => $this->product_spec_id,
                    'productCode' => $this->product_code,
                    'specVersion' => $this->spec_version,
                ],
            ],

            // Action panel (V1.4 §11.2)
            'actionPanel' => [
                'requiredAction' => $action === null ? null : [
                    'value' => $action,
                    'label' => AnalysisUserAction::label($action),
                    'ruleSource' => AnalysisUserAction::ruleSource($action),
                    'requiresReason' => AnalysisUserAction::requiresReason($action),
                ],
                'requiredActionReason' => $this->required_action_reason,
                'allowedActions' => array_map(fn ($a) => [
                    'value' => $a,
                    'label' => AnalysisUserAction::label($a),
                    'requiresReason' => AnalysisUserAction::requiresReason($a),
                ], $allowed),
            ],

            // Attempt timeline (V1.4 §11.1 "Attempts / Timeline")
            'attemptCount' => (int) $this->attempt_count,
            'maxAttempts' => (int) $this->max_attempts,
            'attempts' => $this->attempts->map(fn ($a) => [
                'id' => $a->id,
                'attemptNo' => (int) $a->attempt_no,
                'status' => [
                    'value' => $a->status,
                    'label' => ActiveAnalysisStatus::label($a->status),
                    'tone' => ActiveAnalysisStatus::tone($a->status),
                ],
                'latestMessage' => $a->latest_message,
                'triggeredBy' => $a->triggered_by,
                'startedAt' => $a->started_at?->toIso8601String(),
                'finishedAt' => $a->finished_at?->toIso8601String(),
                'isRepeat' => (bool) $a->is_repeat,
                'requestReason' => $a->request_reason,
            ])->values(),

            // 6-element comparison (V1.4 §12)
            'elements' => AnalysisElementResultResource::collection($elements),
            'elementSummary' => $this->element_summary,

            // Hold / cancellation metadata
            'hold' => $this->held_at === null ? null : [
                'heldAt' => $this->held_at?->toIso8601String(),
                'heldByUserId' => $this->held_by_user_id,
                'reason' => $this->hold_reason,
            ],
            'cancellation' => $this->cancelled_at === null ? null : [
                'cancelledAt' => $this->cancelled_at?->toIso8601String(),
                'cancelledByUserId' => $this->cancelled_by_user_id,
                'reason' => $this->cancellation_reason,
            ],

            // Reference links (V1.4 §11.1 "Reference Links")
            'referenceLinks' => [
                'order' => $this->order_id ? ['routePath' => "/orders/loading-orders/{$this->order_id}"] : null,
                'plantVisit' => $this->plant_visit_id ? ['routePath' => "/operations/active-plant-visits/{$this->plant_visit_id}"] : null,
                'loadingControl' => $this->loading_operation_id ? ['routePath' => "/operations/loading-control/loadings/{$this->loading_operation_id}"] : null,
                'analysisDevice' => $this->device_id ? ['routePath' => "/analysis-quality/analysis-devices/{$this->device_id}"] : null,
                'resultRecord' => $this->related_result_id ? ['routePath' => "/analysis-quality/results-quality-decisions/{$this->related_result_id}"] : null,
                'eventJournal' => ['routePath' => "/alarms-events/event-journal?entity=analysis&entityId={$this->id}"],
                'auditTrail' => ['routePath' => "/alarms-events/audit-trail?entity=analysis&entityId={$this->id}"],
            ],

            'relatedResultId' => $this->related_result_id,
            'closedAt' => $this->closed_at?->toIso8601String(),

            'correlationId' => $this->correlation_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
