<?php

namespace App\Http\Resources;

use App\Enums\AuthMediumType;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthMediumResource extends JsonResource
{
    public function toArray($request): array
    {
        // Admin-panel display rule (2026-05-31): the full TAN value is
        // exposed for list + detail rendering. `displayIdentifier` now
        // carries the full TAN when available (legacy rows fall back to
        // the stored masked form). The new `tan` field is the explicit
        // source-of-truth field for the FE — only populated for TAN rows.
        // `identifier_hash` is still never emitted.
        $fullTan = $this->identifier_value;

        return [
            'id' => $this->id,
            'mediumType' => $this->medium_type,
            'displayIdentifier' => $fullTan ?? $this->display_identifier,
            'tan' => $this->medium_type === AuthMediumType::TAN ? $fullTan : null,
            'driverId' => $this->driver_id,
            'status' => $this->status,
            'isSingleUse' => (bool) $this->is_single_use,
            'issuedAt' => $this->issued_at?->toIso8601String(),
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'usedAt' => $this->used_at?->toIso8601String(),
            'revokedAt' => $this->revoked_at?->toIso8601String(),
            'revocationReason' => $this->revocation_reason,
            'orderId' => $this->order_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
