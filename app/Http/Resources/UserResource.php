<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->status;
        $tone = match ($status) {
            'active' => 'success',
            'inactive' => 'neutral',
            'locked' => 'warning',
            'disabled' => 'danger',
            default => 'neutral',
        };

        return [
            'id' => $this->id,
            'username' => $this->username,
            'fullName' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'preferredLanguage' => $this->preferred_language,
            'status' => [
                'value' => $status,
                'label' => ucfirst($status),
                'tone' => $tone,
            ],
            'isActive' => (bool) $this->is_active,
            'isLocked' => (bool) $this->is_locked,
            'lockedAt' => $this->locked_at?->toIso8601String(),
            'lockedReason' => $this->locked_reason,
            'disabledAt' => $this->disabled_at?->toIso8601String(),
            'disabledReason' => $this->disabled_reason,
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->getRoleNames(),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
