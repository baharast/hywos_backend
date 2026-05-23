<?php

namespace App\Services\Events;

use App\Enums\EventSeverity;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Models\EventLog;
use Illuminate\Database\Eloquent\Model;

class EventLogger
{
    /**
     * Record an operational/system event.
     *
     * @param string            $eventType   e.g. "gate.entry.approved", "loading.started"
     * @param Model|string|null $entity      Affected entity (model or entity_type string), or null
     * @param string|null       $message     Human-readable summary
     * @param array|null        $details     Extra context as JSON
     * @param string|null       $category    One of App\Enums\EventCategory
     * @param string            $severity    One of App\Enums\EventSeverity (default info)
     * @param string|null       $entityId    Required when $entity is a string
     */
    public function record(
        string $eventType,
        Model|string|null $entity = null,
        ?string $message = null,
        ?array $details = null,
        ?string $category = null,
        string $severity = EventSeverity::INFO,
        ?string $entityId = null
    ): EventLog {
        $entityType = null;
        $resolvedEntityId = null;
        if ($entity instanceof Model) {
            $entityType = $entity->getMorphClass();
            $resolvedEntityId = (string) $entity->getKey();
        } elseif (is_string($entity)) {
            $entityType = $entity;
            $resolvedEntityId = $entityId;
        }

        $request = request();
        /** @var \App\Models\User|null $actor */
        $actor = $request ? $request->user() : null;

        return EventLog::create([
            'event_type' => $eventType,
            'event_category' => $category,
            'severity' => $severity,
            'entity_type' => $entityType,
            'entity_id' => $resolvedEntityId,
            'actor_user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'message' => $message,
            'details' => $details,
            'correlation_id' => $this->correlationId(),
            'ip_address' => $request?->ip(),
            'occurred_at' => now(),
        ]);
    }

    protected function correlationId(): ?string
    {
        $request = request();
        if (! $request) {
            return null;
        }
        $cid = $request->attributes->get(CorrelationIdMiddleware::ATTRIBUTE);
        return $cid ? (string) $cid : null;
    }
}
