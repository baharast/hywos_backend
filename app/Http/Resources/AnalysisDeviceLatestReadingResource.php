<?php

namespace App\Http\Resources;

use App\Enums\GasComponent;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalysisDeviceLatestReadingResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'component' => $this->component,
            'componentLabel' => GasComponent::label($this->component),
            'value' => $this->value,
            'unit' => $this->unit,
            'validity' => $this->validity,
            'measuredAt' => $this->measured_at?->toIso8601String(),
        ];
    }
}
