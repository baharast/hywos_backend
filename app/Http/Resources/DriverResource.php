<?php

namespace App\Http\Resources;

use App\Enums\DriverStatus;
use App\Enums\IdentificationStatus;
use App\Enums\TrainingStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status; // derived accessor
        $statusTone = match ($status) {
            DriverStatus::ACTIVE => 'success',
            DriverStatus::BLOCKED => 'danger',
            DriverStatus::INACTIVE => 'offline',
            default => 'neutral',
        };

        $training = $this->training_status ?? TrainingStatus::UNKNOWN;
        $trainingTone = match ($training) {
            TrainingStatus::VALID => 'success',
            TrainingStatus::EXPIRED => 'danger',
            TrainingStatus::MISSING => 'warning',
            TrainingStatus::NOT_REQUIRED => 'neutral',
            default => 'neutral',
        };

        $identification = $this->identification_status;
        $identTone = match ($identification) {
            IdentificationStatus::CHIP_ASSIGNED => 'success',
            IdentificationStatus::TAN_AVAILABLE => 'info',
            IdentificationStatus::BLOCKED => 'danger',
            IdentificationStatus::EXPIRED => 'warning',
            default => 'warning',
        };
        $identLabel = IdentificationStatus::label($identification ?? IdentificationStatus::MISSING);

        $employerName = $this->whenLoaded('employerCompany')
            ? optional($this->employerCompany)->name
            : null;
        $operatorName = $this->whenLoaded('operatorCompany')
            ? optional($this->operatorCompany)->name
            : null;

        return [
            'id' => $this->id,
            'driverCode' => $this->driver_code,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => $this->full_name,
            'initials' => $this->initials,

            'nationalIdLast4' => $this->national_id_last4,
            'licenseNo' => $this->license_no,
            'licenseExpiryDate' => $this->license_expiry_date?->toDateString(),

            'phone' => $this->phone,
            'email' => $this->email,

            'preferredLanguage' => $this->preferred_culture_code,

            'status' => [
                'value' => $status,
                'label' => DriverStatus::label($status ?? DriverStatus::UNKNOWN),
                'tone' => $statusTone,
            ],
            'trainingStatus' => [
                'value' => $training,
                'label' => TrainingStatus::label($training),
                'tone' => $trainingTone,
            ],
            'trainingValidUntil' => $this->training_valid_until?->toDateString(),
            'identificationStatus' => [
                'value' => $identification,
                'label' => $identLabel,
                'tone' => $identTone,
            ],

            'blockStatus' => $this->block_status,
            'blockReason' => $this->block_reason,
            'blockedAt' => $this->blocked_at?->toIso8601String(),

            'isActive' => (bool) $this->is_active,

            'employerCompanyId' => $this->employer_company_id,
            'employerCompanyName' => $employerName,
            'operatorCompanyId' => $this->operator_company_id,
            'operatorCompanyName' => $operatorName,

            'avatarFileId' => $this->avatar_file_id,
            'notes' => $this->notes,
            'hasNotes' => ! empty($this->notes),

            // Placeholder until plant_visits module exists.
            'lastVisitAt' => null,

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
