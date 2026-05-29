<?php

namespace App\Http\Resources;

use App\Enums\LogbookArea;
use App\Enums\LogbookCategory;
use App\Enums\LogbookFollowUpStatus;
use App\Enums\LogbookSeverity;
use App\Services\AlarmsEvents\LogbookService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Composite row for V1 §7.5 Safety & Operations Logbook.
 *
 * Detail mode adds `corrections[]` (up to 20 newest-first) via
 * `->additional(...)` on the controller. List mode leaves it null.
 */
class LogbookEntryResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\LogbookEntry $entry */
        $entry = $this->resource;

        $svc = app(LogbookService::class);
        $derivedStatus = $svc->deriveFollowUpStatus($entry);

        $category = $entry->category;
        $severity = $entry->severity;
        $area = $entry->area;

        return [
            'id' => $entry->id,
            'logbookId' => $this->formatLogbookId(),

            'shiftLabel' => $entry->shift_label,

            'category' => [
                'value' => $category,
                'label' => LogbookCategory::label($category ?? ''),
                'tone' => LogbookCategory::tone($category ?? ''),
            ],
            'severity' => [
                'value' => $severity,
                'label' => LogbookSeverity::label($severity ?? ''),
                'tone' => LogbookSeverity::tone($severity ?? ''),
            ],
            'area' => $area === null ? null : [
                'value' => $area,
                'label' => LogbookArea::label($area),
            ],

            'title' => $entry->title,
            'description' => $entry->description,
            'actionTaken' => $entry->action_taken,

            'relatedObject' => [
                'type' => $entry->related_entity_type,
                'shortType' => $entry->related_entity_type === null
                    ? null
                    : class_basename($entry->related_entity_type),
                'id' => $entry->related_entity_id,
            ],

            'linkedRecords' => [
                'alarmId' => $entry->linked_alarm_id,
                'eventLogId' => $entry->linked_event_log_id,
                'clarificationCaseId' => $entry->linked_clarification_case_id,
            ],

            'followUp' => [
                'required' => (bool) $entry->follow_up_required,
                'owner' => [
                    'userId' => $entry->follow_up_owner_user_id,
                    'role' => $entry->follow_up_owner_role,
                ],
                'dueAt' => $entry->follow_up_due_at?->toIso8601String(),
                'status' => [
                    'value' => $derivedStatus,
                    'label' => LogbookFollowUpStatus::label($derivedStatus),
                    'tone' => LogbookFollowUpStatus::tone($derivedStatus),
                ],
                'completedAt' => $entry->follow_up_completed_at?->toIso8601String(),
                'completedByUserId' => $entry->follow_up_completed_by_user_id,
                'completionNote' => $entry->follow_up_completion_note,
            ],

            'handoverFlag' => (bool) $entry->handover_flag,

            'createdBy' => [
                'userId' => $entry->created_by_user_id,
                'name' => $entry->created_by_name,
            ],
            'correlationId' => $entry->correlation_id,

            'createdAt' => $entry->created_at?->toIso8601String(),
            'updatedAt' => $entry->updated_at?->toIso8601String(),

            // Detail-mode extras — last 20 corrections newest-first.
            'corrections' => $this->additional['corrections'] ?? null,
        ];
    }

    protected function formatLogbookId(): string
    {
        /** @var \App\Models\LogbookEntry $entry */
        $entry = $this->resource;
        $year = $entry->created_at?->format('Y') ?? date('Y');
        // Use a short hash of the UUID for FE display so operators have
        // a stable 6-char identifier without revealing the full uuid.
        $hash = strtoupper(substr(hash('crc32b', (string) $entry->id), 0, 6));
        return sprintf('LBK-%s-%s', $year, $hash);
    }
}
