<?php

namespace App\Http\Resources;

use App\Enums\BlockingImpact;
use App\Enums\ClarificationEntityType;
use App\Enums\ClarificationPrimaryActionType;
use App\Enums\ClarificationSeverity;
use App\Enums\ClarificationSource;
use App\Enums\ClarificationStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class ClarificationCaseResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status ?? ClarificationStatus::OPEN;
        $severity = $this->severity ?? ClarificationSeverity::NORMAL;
        $blockingImpact = $this->blocking_impact ?? BlockingImpact::NONE;
        $source = $this->source;

        $primaryActionValue = $this->primary_action;
        $primaryAction = $primaryActionValue === null
            ? null
            : [
                'value' => $primaryActionValue,
                'label' => ClarificationPrimaryActionType::label($primaryActionValue),
            ];

        return [
            'id' => $this->id,
            'caseNo' => $this->case_no,
            'status' => [
                'value' => $status,
                'label' => ClarificationStatus::label($status),
                'tone' => ClarificationStatus::tone($status),
            ],
            'severity' => [
                'value' => $severity,
                'label' => ClarificationSeverity::label($severity),
                'tone' => ClarificationSeverity::tone($severity),
            ],
            'source' => $source === null
                ? null
                : [
                    'value' => $source,
                    'label' => ClarificationSource::label($source),
                ],
            'blockingImpact' => [
                'value' => $blockingImpact,
                'label' => BlockingImpact::label($blockingImpact),
                'tone' => BlockingImpact::tone($blockingImpact),
            ],
            'primaryAction' => $primaryAction,
            'actionNeeded' => $this->action_needed,

            'category' => $this->category,
            'title' => $this->title,
            'description' => $this->description,
            'reasonCode' => $this->reason_code,

            'entity' => [
                'type' => $this->entity_type,
                'id' => $this->entity_id,
                'label' => $this->entity_label,
                'routePath' => ClarificationEntityType::routePathFor(
                    $this->entity_type,
                    $this->entity_id
                ),
            ],

            'related' => [
                'plantVisitId' => $this->related_plant_visit_id,
                'orderId' => $this->related_order_id,
                'driverId' => $this->related_driver_id,
                'trailerId' => $this->related_trailer_id,
            ],

            'ownerRole' => $this->owner_role,
            'assignedToUserId' => $this->assigned_to_user_id,
            'isBlocking' => (bool) $this->is_blocking,

            'openedAt' => $this->opened_at?->toIso8601String(),
            'acknowledgedAt' => $this->acknowledged_at?->toIso8601String(),
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'closedAt' => $this->closed_at?->toIso8601String(),

            'resolutionNote' => $this->resolution_note,

            'correlationId' => $this->correlation_id,
            'notes' => $this->notes,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
