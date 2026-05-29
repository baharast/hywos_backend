<?php

namespace App\Http\Resources;

use App\Enums\EventSeverity;
use App\Services\AlarmsEvents\SecurityEventsService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Composite row for V1 §7.6 Security Events.
 *
 * Per V1 §7.6 forbidden actions: do not expose sensitive full chip/TAN
 * values, passwords, service tokens or unrestricted payloads. The
 * resource therefore intentionally omits raw `details` and uses a
 * masked IP in `sourceLocation`. The detail endpoint adds a sanitized
 * `details` snapshot via `->additional(...)` — also subject to the
 * "no sensitive payloads" rule.
 */
class SecurityEventResource extends JsonResource
{
    public function toArray($request): array
    {
        $svc = app(SecurityEventsService::class);
        $composite = $svc->enrichRow($this->resource);

        $severity = $this->severity ?? EventSeverity::INFO;

        return [
            'id' => $this->id,
            'eventId' => $this->formatEventId(),

            'occurredAt' => $this->occurred_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),

            'eventType' => $this->event_type,
            'severity' => [
                'value' => $severity,
                'label' => ucfirst($severity),
            ],

            // DERIVED classifications.
            'securityCategory' => $composite['securityCategory'],
            'result' => $composite['result'],
            'risk' => $composite['risk'],
            'review' => $composite['review'],

            'actor' => [
                'userId' => $this->actor_user_id,
                'name' => $this->actor_name,
            ],

            'identityMedium' => $composite['identityMedium'],
            'sourceLocation' => $composite['sourceLocation'],

            'affectedObject' => [
                'type' => $this->entity_type,
                'shortType' => $this->entity_type === null
                    ? null
                    : class_basename($this->entity_type),
                'id' => $this->entity_id,
            ],

            'message' => $this->message,
            'correlationId' => $this->correlation_id,

            'safeActions' => $composite['safeActions'],

            // Detail-mode sanitized payload (chip/TAN/passwords scrubbed
            // by the controller before passing in).
            'details' => $this->additional['details'] ?? null,

            // §7.6 boundary note rendered as a neutral chip in detail.
            'restrictedNote' => 'Restricted security data. Do not export to unauthorized recipients.',
        ];
    }

    protected function formatEventId(): string
    {
        $year = $this->occurred_at?->format('Y')
            ?? $this->created_at?->format('Y')
            ?? date('Y');
        return sprintf('SEC-%s-%06d', $year, (int) $this->id);
    }
}
