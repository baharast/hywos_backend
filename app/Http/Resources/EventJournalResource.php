<?php

namespace App\Http\Resources;

use App\Enums\EventSeverity;
use App\Services\AlarmsEvents\EventJournalService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Composite row for V1 §7.4 Event Journal (default subview).
 *
 * Detail mode adds `details` JSON payload and `technicalPayload` flag
 * via `->additional(...)`. List mode keeps `details` null so the row
 * stays compact.
 */
class EventJournalResource extends JsonResource
{
    public function toArray($request): array
    {
        $svc = app(EventJournalService::class);
        $composite = $svc->enrichRow($this->resource);

        $severity = $this->severity ?? EventSeverity::INFO;

        return [
            'id' => $this->id,
            'eventId' => $this->formatEventId(),

            'occurredAt' => $this->occurred_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),

            'eventType' => $this->event_type,
            'eventCategory' => $this->event_category,
            'journalCategory' => $composite['journalCategory'],
            'result' => $composite['result'],

            'severity' => [
                'value' => $severity,
                'label' => EventSeverity::label($severity),
            ],

            'actor' => [
                'userId' => $this->actor_user_id,
                'name' => $this->actor_name,
            ],

            'affectedObject' => [
                'type' => $this->entity_type,
                'shortType' => $this->entity_type === null
                    ? null
                    : class_basename($this->entity_type),
                'id' => $this->entity_id,
            ],

            'message' => $this->message,

            'correlationId' => $this->correlation_id,
            'ipAddress' => $this->ip_address,

            'relatedRecords' => $composite['relatedRecords'],

            // Detail-mode extras — full technical payload only when
            // explicitly requested via the detail endpoint.
            'details' => $this->additional['details'] ?? null,
        ];
    }

    protected function formatEventId(): string
    {
        $year = $this->occurred_at?->format('Y')
            ?? $this->created_at?->format('Y')
            ?? date('Y');
        return sprintf('EVT-%s-%06d', $year, (int) $this->id);
    }
}
