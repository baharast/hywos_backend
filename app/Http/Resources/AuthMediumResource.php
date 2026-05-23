<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthMediumResource extends JsonResource
{
    public function toArray($request): array
    {
        // SECURITY: never include identifier_value (raw TAN/chip data) or identifier_hash.
        return [
            'id' => $this->id,
            'mediumType' => $this->medium_type,
            'displayIdentifier' => $this->display_identifier,
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
