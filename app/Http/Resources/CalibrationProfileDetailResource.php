<?php

namespace App\Http\Resources;

use App\Enums\CalibrationLockoutBehavior;
use App\Enums\CalibrationProfileStatus;
use App\Enums\GasComponent;
use Illuminate\Http\Resources\Json\JsonResource;

class CalibrationProfileDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $configured = $this->components->pluck('component')->all();
        $required = GasComponent::all();
        $missing = array_values(array_diff($required, $configured));

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
            'notes' => $this->notes,

            'activatedAt' => $this->activated_at?->toIso8601String(),
            'activatedByUserId' => $this->activated_by_user_id,
            'retiredAt' => $this->retired_at?->toIso8601String(),
            'retiredByUserId' => $this->retired_by_user_id,

            'isEditable' => CalibrationProfileStatus::isEditable($this->status),
            'requiresReasonForEdit' => CalibrationProfileStatus::requiresReasonForEdit($this->status),

            'componentCompleteness' => [
                'configured' => count($configured),
                'required' => count($required),
                'missing' => array_map(fn ($c) => [
                    'component' => $c,
                    'label' => GasComponent::label($c),
                ], $missing),
                'complete' => count($missing) === 0,
            ],

            'components' => CalibrationComponentResource::collection(
                $this->components->sortBy(fn ($c) => GasComponent::displayOrder($c->component))->values()
            ),

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
