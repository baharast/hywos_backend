<?php

namespace App\Http\Resources;

use App\Services\AlarmsEvents\AuditTrailService;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Composite row for V1 §8 Change Log / Audit Trail.
 *
 * Wire shape is camelCase; underlying audit_logs row is snake_case.
 * Both list + detail endpoints share this shape — detail mode adds
 * `oldValues` / `newValues` full payload via `->additional(...)` on the
 * controller. List mode keeps them null to keep the row compact.
 */
class AuditTrailResource extends JsonResource
{
    public function toArray($request): array
    {
        $svc = app(AuditTrailService::class);
        $composite = $svc->enrichRow($this->resource);

        return [
            'id' => $this->id,
            'auditId' => $this->formatAuditId(),

            'createdAt' => $this->created_at?->toIso8601String(),

            // Actor block.
            'actor' => [
                'userId' => $this->actor_user_id,
                'name' => $this->actor_name,
            ],

            // Changed entity.
            'entity' => [
                'type' => $this->entity_type,
                'shortType' => $this->entity_type === null
                    ? null
                    : class_basename($this->entity_type),
                'id' => $this->entity_id,
            ],

            // Raw action + derived classifications.
            'action' => $this->action,
            'changeCategory' => $composite['changeCategory'],
            'actionType' => $composite['actionType'],
            'retentionClass' => $composite['retentionClass'],
            'approval' => $composite['approval'],

            // Compact before-after preview (full payload in detail mode).
            'beforeAfterSummary' => $composite['beforeAfterSummary'],

            // Reason.
            'reasonCode' => $this->reason_code,
            'reason' => $this->reason,

            // Correlation + traceability.
            'correlationId' => $this->correlation_id,
            'ipAddress' => $this->ip_address,

            // FE deep-links.
            'relatedRecords' => $composite['relatedRecords'],

            // Detail-mode extras — null when used in list mode.
            'oldValues' => $this->additional['oldValues'] ?? null,
            'newValues' => $this->additional['newValues'] ?? null,
            'userAgent' => $this->additional['userAgent'] ?? null,
            'sessionId' => $this->additional['sessionId'] ?? null,
        ];
    }

    /**
     * Format the audit identifier as `AUD-YYYY-NNNNNN` for FE display.
     * The numeric `id` stays available as `id` for API joins.
     */
    protected function formatAuditId(): string
    {
        $year = $this->created_at?->format('Y') ?? date('Y');
        return sprintf('AUD-%s-%06d', $year, (int) $this->id);
    }
}
