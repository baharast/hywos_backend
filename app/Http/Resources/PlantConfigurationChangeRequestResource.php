<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlantConfigurationChangeRequestResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'plantConfigurationId' => $this->plant_configuration_id,
            'affectedObject' => [
                'type' => $this->affected_object_type,
                'id' => $this->affected_object_id,
                'label' => $this->affected_object_label,
            ],
            'changeType' => $this->change_type,
            'currentValues' => $this->current_values,
            'proposedValues' => $this->proposed_values,
            'reason' => $this->reason,
            'reasonCode' => $this->reason_code,
            'status' => $this->status,
            'submittedAt' => $this->submitted_at?->toIso8601String(),
            'submittedByUserId' => $this->submitted_by_user_id,
            'approvedAt' => $this->approved_at?->toIso8601String(),
            'approvedByUserId' => $this->approved_by_user_id,
            'rejectedAt' => $this->rejected_at?->toIso8601String(),
            'rejectedByUserId' => $this->rejected_by_user_id,
            'rejectionNote' => $this->rejection_note,
            'appliedAt' => $this->applied_at?->toIso8601String(),
            'appliedByUserId' => $this->applied_by_user_id,
            'correlationId' => $this->correlation_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
