<?php

namespace App\Http\Resources;

use App\Enums\PlantConfigurationStatus;
use Illuminate\Http\Resources\Json\JsonResource;

class PlantConfigurationResource extends JsonResource
{
    public function toArray($request): array
    {
        $status = $this->status ?? PlantConfigurationStatus::NOT_CONFIGURED;

        return [
            'id' => $this->id,
            'status' => [
                'value' => $status,
                'label' => ucwords(str_replace('_', ' ', $status)),
                'tone' => PlantConfigurationStatus::tone($status),
            ],
            'version' => (int) ($this->version ?? 1),
            'isLocked' => $status === PlantConfigurationStatus::ACTIVE_LOCKED,
            'companyId' => $this->company_id,
            'companyName' => $this->company_name,
            'companyCode' => $this->company_code,
            'siteId' => $this->site_id,
            'siteName' => $this->site_name,
            'siteCode' => $this->site_code,
            'plantType' => $this->plant_type,
            'defaultLanguage' => $this->default_language,
            'timeZone' => $this->time_zone,
            'counts' => [
                'plantAreas' => $this->whenCounted('plantAreas'),
                'gates' => $this->whenCounted('gates'),
                'terminalsPanels' => $this->whenCounted('terminalsPanels'),
                'bayLines' => $this->whenCounted('bayLines'),
                'parkingAreas' => $this->whenCounted('parkings'),
            ],
            'validation' => $this->validation_summary,
            'activatedAt' => $this->activated_at?->toIso8601String(),
            'activatedByUserId' => $this->activated_by_user_id,
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
