<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChipCardAssignmentHistoryItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'entityType' => $this->entity_type,
            'entityId' => $this->entity_id,
            'entityLabel' => $this->entity_label,
            'actor' => $this->actor_user_id,
            'reason' => $this->reason,
            'reasonCode' => $this->reason_code,
            'correlationId' => $this->correlation_id,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
