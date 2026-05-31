<?php

namespace App\Http\Resources;

use App\Enums\AuthMediumStatus;
use App\Enums\TanStatus;
use App\Enums\TanUsageState;
use App\Models\AuthMedium;
use Illuminate\Http\Resources\Json\JsonResource;

class TanResource extends JsonResource
{
    public function toArray($request): array
    {
        // Admin-panel display rule (2026-05-31): the full TAN value is
        // returned in `tan` for list + detail rendering. `tanMasked` is
        // kept for backward compatibility and now also carries the full
        // value when it is available, so older FE code that still reads
        // `tanMasked` no longer renders "***-XXXX". `identifier_hash` is
        // still never emitted.
        $fullTan = $this->identifier_value;
        $statusValue = $this->deriveStatus($this->resource);
        $usageValue = $this->usage_state ?? TanUsageState::UNUSED;

        $driverLabel = null;
        $driverCode = null;
        if ($this->relationLoaded('driver') && $this->driver) {
            $name = trim(($this->driver->first_name ?? '') . ' ' . ($this->driver->last_name ?? ''));
            $driverCode = $this->driver->driver_code ?? null;
            $driverLabel = $driverCode ? "{$name} ({$driverCode})" : ($name ?: $this->driver_id);
        }

        return [
            'id' => $this->id,
            'tanReference' => $this->tan_reference,
            // Full value for new rows; legacy ***-XXXX fallback for older rows
            // that were created before the plain-text persistence change.
            'tan' => $fullTan ?? $this->display_identifier ?? $this->tan_masked,
            'tanMasked' => $fullTan ?? $this->tan_masked ?? $this->display_identifier,

            'status' => [
                'value' => $statusValue,
                'label' => TanStatus::label($statusValue),
                'tone' => TanStatus::tone($statusValue),
            ],
            'usageState' => [
                'value' => $usageValue,
                'label' => TanUsageState::label($usageValue),
                'tone' => TanUsageState::tone($usageValue),
            ],

            'assignedDriverId' => $this->driver_id,
            'assignedDriverLabel' => $driverLabel,
            'assignedDriverCode' => $driverCode,

            'relatedPlantVisitId' => $this->related_plant_visit_id,
            'relatedPlantVisitNo' => null,
            'relatedOrderId' => $this->order_id,
            'relatedOrderNo' => null,
            'relatedTerminalSessionId' => $this->related_terminal_session_id,
            'usageContextSummary' => null,

            'validFrom' => $this->valid_from?->toIso8601String()
                ?? $this->issued_at?->toIso8601String(),
            'expiresAt' => $this->expires_at?->toIso8601String(),

            'consumedAt' => $this->consumed_at?->toIso8601String(),
            'consumptionCount' => (int) ($this->consumption_count ?? 0),

            'reason' => $this->reason,

            'createdBy' => $this->created_by_user_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),

            'revokedAt' => $this->revoked_at?->toIso8601String(),
            'revokedBy' => $this->revoked_by_user_id,
            'revocationReason' => $this->revocation_reason,

            'lastEventAt' => null,
            'lastEventSummary' => null,
        ];
    }

    protected function deriveStatus(AuthMedium $tan): string
    {
        if ($tan->status === AuthMediumStatus::BLOCKED) {
            return TanStatus::BLOCKED;
        }
        if (! is_null($tan->revoked_at)) {
            return TanStatus::REVOKED;
        }
        if (! is_null($tan->consumed_at)) {
            return TanStatus::CONSUMED;
        }
        if ($tan->status === AuthMediumStatus::EXPIRED) {
            return TanStatus::EXPIRED;
        }
        if ($tan->expires_at && $tan->expires_at->isPast()) {
            return TanStatus::EXPIRED;
        }
        if ($tan->valid_from && $tan->valid_from->isFuture()) {
            return TanStatus::PENDING;
        }
        return TanStatus::ACTIVE;
    }
}
