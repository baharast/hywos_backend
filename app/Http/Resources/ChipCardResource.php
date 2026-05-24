<?php

namespace App\Http\Resources;

use App\Enums\AuthMediumStatus;
use App\Enums\ChipCardAssignmentState;
use App\Enums\ChipCardLifecycleStatus;
use App\Enums\ChipCardType;
use App\Enums\ChipUsageResult;
use Illuminate\Http\Resources\Json\JsonResource;

class ChipCardResource extends JsonResource
{
    public function toArray($request): array
    {
        // SECURITY: never include identifier_value or identifier_hash.
        $rawStatus = $this->status ?? AuthMediumStatus::ACTIVE;
        $derivedExpired = $this->expires_at && $this->expires_at->isPast();
        $lifecycleValue = $derivedExpired && $rawStatus === AuthMediumStatus::ACTIVE
            ? ChipCardLifecycleStatus::EXPIRED
            : $rawStatus;

        $assignmentState = $this->assignment_state ?? ChipCardAssignmentState::UNASSIGNED;

        $cardType = $this->card_type;
        $usageResult = $this->last_usage_result;

        return [
            'id' => $this->id,
            'cardCode' => $this->card_code,
            'serialNumber' => $this->serial_number,
            'maskedUid' => $this->masked_uid ?? $this->display_identifier,
            'cardType' => $cardType ? [
                'value' => $cardType,
                'label' => ChipCardType::label($cardType),
            ] : null,

            'lifecycleStatus' => [
                'value' => $lifecycleValue,
                'label' => ChipCardLifecycleStatus::label($lifecycleValue),
                'tone' => ChipCardLifecycleStatus::tone($lifecycleValue),
            ],
            'assignmentState' => [
                'value' => $assignmentState,
                'label' => ChipCardAssignmentState::label($assignmentState),
                'tone' => ChipCardAssignmentState::tone($assignmentState),
            ],

            'assignedEntityType' => $this->resolveAssignedEntityType(),
            'assignedEntityId' => $this->resolveAssignedEntityId(),
            'assignedEntityLabel' => $this->resolveAssignedEntityLabel(),

            'validFrom' => $this->issued_at?->toIso8601String(),
            'validUntil' => $this->expires_at?->toIso8601String(),
            'isExpired' => (bool) $derivedExpired,
            'isExpiringSoon' => $this->expires_at && ! $derivedExpired
                ? $this->expires_at->diffInDays(now()) <= 30
                : false,

            'lastUsedAt' => $this->last_used_at?->toIso8601String(),
            'lastUsedContext' => $this->last_used_context,
            'lastUsedSource' => $this->last_used_source,
            'lastUsageResult' => $usageResult ? [
                'value' => $usageResult,
                'label' => ChipUsageResult::label($usageResult),
                'tone' => ChipUsageResult::tone($usageResult),
            ] : null,

            'replacementOfCardId' => $this->replacement_of_card_id,
            'replacedByCardId' => $this->replaced_by_card_id,
            'replacementReason' => $this->replacement_reason,

            'lostAt' => $this->lost_at?->toIso8601String(),
            'defectiveAt' => $this->defective_at?->toIso8601String(),
            'archivedAt' => $this->archived_at?->toIso8601String(),

            'notes' => null,
            'hasNotes' => false,
            'issuedAt' => $this->issued_at?->toIso8601String(),
            'issuedBy' => $this->created_by_user_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    protected function resolveAssignedEntityType(): ?string
    {
        if ($this->assignment_state !== ChipCardAssignmentState::ASSIGNED) {
            return null;
        }
        if ($this->driver_id) {
            return 'driver';
        }
        if ($this->trailer_id) {
            return 'trailer';
        }
        return null;
    }

    protected function resolveAssignedEntityId(): ?string
    {
        if ($this->assignment_state !== ChipCardAssignmentState::ASSIGNED) {
            return null;
        }
        return $this->driver_id ?? $this->trailer_id;
    }

    protected function resolveAssignedEntityLabel(): ?string
    {
        if ($this->assignment_state !== ChipCardAssignmentState::ASSIGNED) {
            return null;
        }
        if ($this->driver_id && $this->relationLoaded('driver') && $this->driver) {
            $name = trim(($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? ''));
            $code = $this->driver->driver_code ?? null;
            return $code ? "{$name} ({$code})" : ($name ?: $this->driver_id);
        }
        if ($this->trailer_id && $this->relationLoaded('trailer') && $this->trailer) {
            $label = $this->trailer->trailer_label ?? $this->trailer->trailer_code;
            return $label ?: $this->trailer_id;
        }
        return $this->driver_id ?? $this->trailer_id;
    }
}
