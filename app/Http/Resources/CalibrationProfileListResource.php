<?php

namespace App\Http\Resources;

use App\Enums\CalibrationLockoutBehavior;
use App\Enums\CalibrationProfileStatus;
use App\Enums\GasComponent;
use Illuminate\Http\Resources\Json\JsonResource;

class CalibrationProfileListResource extends JsonResource
{
    public function toArray($request): array
    {
        $configured = null;
        if ($this->relationLoaded('components')) {
            $configured = $this->components->count();
        }

        return [
            'id' => $this->id,
            'deviceId' => $this->device_id,
            'deviceBmk' => $this->device_bmk,
            'deviceName' => $this->device_name,
            'calibrationMedium' => $this->calibration_medium,
            'certificateRef' => $this->certificate_ref,
            'profileVersion' => $this->profile_version,
            'status' => [
                'value' => $this->status,
                'label' => CalibrationProfileStatus::label($this->status),
                'tone' => CalibrationProfileStatus::tone($this->status),
            ],
            'calibrationStatus' => [
                'value' => $this->calibration_status,
                'label' => CalibrationProfileStatus::calibrationStatusLabel($this->calibration_status),
                'tone' => CalibrationProfileStatus::calibrationStatusTone($this->calibration_status),
            ],
            'lockoutBehavior' => [
                'value' => $this->lockout_behavior,
                'label' => CalibrationLockoutBehavior::label($this->lockout_behavior),
                'tone' => CalibrationLockoutBehavior::tone($this->lockout_behavior),
            ],
            'mediumExpiryAt' => $this->medium_expiry_at?->toIso8601String(),
            'nextDueAt' => $this->next_due_at?->toIso8601String(),
            'lastRunAt' => $this->last_run_at?->toIso8601String(),
            'componentCompleteness' => [
                'configured' => $configured,
                'required' => count(GasComponent::all()),
                'complete' => $configured === null ? null : ($configured >= count(GasComponent::all())),
            ],
            'isEditable' => CalibrationProfileStatus::isEditable($this->status),
            'requiresReasonForEdit' => CalibrationProfileStatus::requiresReasonForEdit($this->status),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
