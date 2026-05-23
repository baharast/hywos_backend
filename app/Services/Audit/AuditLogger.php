<?php

namespace App\Services\Audit;

use App\Http\Middleware\CorrelationIdMiddleware;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    /**
     * Record a sensitive change to an entity.
     *
     * Auth is not enforced yet, so $actor may be null and the record will still be written.
     *
     * @param Model|string  $entity     The affected entity (model instance or entity_type string)
     * @param string        $action     One of App\Enums\AuditAction
     * @param array|null    $oldValues  Snapshot before the change
     * @param array|null    $newValues  Snapshot after the change
     * @param string|null   $reason     Free text reason (required for critical actions per docs)
     * @param string|null   $reasonCode Optional controlled reason code
     * @param string|null   $entityId   Required when $entity is a string
     */
    public function record(
        Model|string $entity,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $reason = null,
        ?string $reasonCode = null,
        ?string $entityId = null
    ): AuditLog {
        if ($entity instanceof Model) {
            $entityType = $entity->getMorphClass();
            $resolvedEntityId = (string) $entity->getKey();
        } else {
            $entityType = $entity;
            $resolvedEntityId = $entityId ?? '';
        }

        $request = request();
        /** @var \App\Models\User|null $actor */
        $actor = $request ? $request->user() : null;

        return AuditLog::create([
            'actor_user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'entity_type' => $entityType,
            'entity_id' => $resolvedEntityId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'reason_code' => $reasonCode,
            'reason' => $reason,
            'correlation_id' => $this->correlationId(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'session_id' => $request && $request->hasSession() ? $request->session()->getId() : null,
        ]);
    }

    /**
     * Convenience: record creation. Stores only new_values.
     */
    public function recordCreated(Model $entity, string $action, ?string $reason = null): AuditLog
    {
        return $this->record($entity, $action, null, $this->snapshotModel($entity), $reason);
    }

    /**
     * Convenience: record diff between an old snapshot and the entity's current state.
     */
    public function recordUpdated(Model $entity, array $oldSnapshot, string $action, ?string $reason = null): AuditLog
    {
        $newSnapshot = $this->snapshotModel($entity);
        return $this->record($entity, $action, $oldSnapshot, $newSnapshot, $reason);
    }

    /**
     * Public helper so callers can snapshot a model before mutating it.
     */
    public function snapshotModel(Model $entity): array
    {
        $array = $entity->toArray();
        foreach (['password', 'remember_token', 'national_id_hash'] as $sensitive) {
            unset($array[$sensitive]);
        }
        return $array;
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
