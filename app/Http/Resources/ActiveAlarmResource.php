<?php

namespace App\Http\Resources;

use App\Enums\AlarmBlockingImpact;
use App\Enums\AlarmCategory;
use App\Enums\AlarmOwnerRole;
use App\Enums\AlarmSeverity;
use App\Enums\AlarmSourceType;
use App\Enums\AlarmStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Composite row for V1 §6.7 Active Alarms.
 *
 * Per V1 §6.10 + §6.8: technical payload (raw OPC UA tags, raw values)
 * is RESTRICTED — surfaced only in the detail endpoint and only when
 * the caller has the future `alarms.view_technical` permission. The
 * list endpoint NEVER returns it.
 */
class ActiveAlarmResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\Alarm $a */
        $a = $this->resource;

        $severity = $a->severity ?? AlarmSeverity::INFO;
        $status = $a->status ?? AlarmStatus::ACTIVE;
        $category = $a->category ?? '';
        $blocking = $a->blocking_impact ?? AlarmBlockingImpact::WARNING_ONLY;
        $source = $a->source_type;
        $owner = $a->owner_role;

        $ageSeconds = $a->first_seen_at ? now()->diffInSeconds($a->first_seen_at) : null;

        return [
            'id' => $a->id,
            'alarmNo' => $a->alarm_no,
            'title' => $a->title,

            'severity' => [
                'value' => $severity,
                'label' => AlarmSeverity::label($severity),
                'tone' => AlarmSeverity::tone($severity),
            ],
            'status' => [
                'value' => $status,
                'label' => AlarmStatus::label($status),
                'tone' => AlarmStatus::tone($status),
            ],
            'category' => [
                'value' => $category,
                'label' => AlarmCategory::label($category),
                'tone' => AlarmCategory::tone($category),
            ],
            'blockingImpact' => [
                'value' => $blocking,
                'label' => AlarmBlockingImpact::label($blocking),
                'tone' => AlarmBlockingImpact::tone($blocking),
                'isBlocking' => AlarmBlockingImpact::isBlocking($blocking),
            ],
            'sourceType' => $source === null ? null : [
                'value' => $source,
                'label' => AlarmSourceType::label($source),
            ],
            'sourceId' => $a->source_id,
            'sourceLabel' => $a->source_label,
            'location' => $a->location,

            'owner' => [
                'role' => $owner === null ? null : [
                    'value' => $owner,
                    'label' => AlarmOwnerRole::label($owner),
                ],
                'userId' => $a->owner_user_id,
                'userName' => $a->owner_user_name,
            ],

            'linkedObject' => [
                'type' => $a->linked_entity_type,
                'shortType' => $a->linked_entity_type === null
                    ? null
                    : class_basename($a->linked_entity_type),
                'id' => $a->linked_entity_id,
                'label' => $a->linked_entity_label,
            ],

            'message' => $a->message,
            'recommendedAction' => $a->recommended_action,
            'alarmCode' => $a->alarm_code,

            // Detail-only physical readings; allowed in list per spec §6.8.
            'value' => [
                'current' => $a->current_value,
                'threshold' => $a->threshold_value,
                'unit' => $a->unit,
            ],

            'firstSeenAt' => $a->first_seen_at?->toIso8601String(),
            'lastSeenAt' => $a->last_seen_at?->toIso8601String(),
            'occurrenceCount' => (int) $a->occurrence_count,
            'ageSeconds' => $ageSeconds,

            'acknowledgement' => [
                'at' => $a->acknowledged_at?->toIso8601String(),
                'byUserId' => $a->acknowledged_by_user_id,
                'byName' => $a->acknowledged_by_name,
            ],
            'inProgress' => [
                'at' => $a->in_progress_at?->toIso8601String(),
                'byUserId' => $a->in_progress_by_user_id,
            ],
            'resolution' => [
                'at' => $a->resolved_at?->toIso8601String(),
                'byUserId' => $a->resolved_by_user_id,
                'byName' => $a->resolved_by_name,
                'reason' => $a->resolution_reason,
                'correctiveAction' => $a->corrective_action,
            ],
            'closedAt' => $a->closed_at?->toIso8601String(),

            'correlationId' => $a->correlation_id,
            'createdAt' => $a->created_at?->toIso8601String(),
            'updatedAt' => $a->updated_at?->toIso8601String(),

            'allowedActions' => $this->allowedActions($a),

            // Detail-mode RESTRICTED technical payload — list mode always null.
            'technicalPayload' => $this->additional['technicalPayload'] ?? null,
        ];
    }

    /**
     * V1 §6.9 server-derived hint about which workflow buttons the FE
     * should render enabled. The FE must STILL gate on permissions —
     * this is a UX convenience, not an authorization decision.
     */
    protected function allowedActions(\App\Models\Alarm $a): array
    {
        $canAcknowledge = $a->status !== AlarmStatus::CLOSED
            && $a->acknowledged_at === null;

        $canAssign = $a->status !== AlarmStatus::CLOSED;

        $canMarkInProgress = in_array($a->status, [
            AlarmStatus::ACKNOWLEDGED, AlarmStatus::ASSIGNED, AlarmStatus::ACTIVE,
        ], true);

        $canResolve = $a->status !== AlarmStatus::CLOSED;

        $canClose = $a->status !== AlarmStatus::CLOSED && (
            ! AlarmSeverity::requiresAcknowledgementBeforeClose($a->severity ?? '')
            || $a->acknowledged_at !== null
        );

        return [
            'acknowledge' => $canAcknowledge,
            'assign' => $canAssign,
            'mark_in_progress' => $canMarkInProgress,
            'resolve' => $canResolve,
            'close' => $canClose,
        ];
    }
}
