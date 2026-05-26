<?php

namespace App\Http\Resources;

use App\Enums\AnalysisElementStatus;
use App\Enums\GasComponent;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row in the 6-element comparison table (V1.4 §12).
 */
class AnalysisElementResultResource extends JsonResource
{
    public function toArray($request): array
    {
        $element = $this->element;
        $status = $this->status ?? AnalysisElementStatus::WAITING;

        return [
            'id' => $this->id,
            'element' => $element === null ? null : [
                'value' => $element,
                'label' => GasComponent::label($element),
            ],
            'measuredValue' => $this->measured_value === null ? null : (float) $this->measured_value,
            'unit' => $this->unit,
            'lowerLimit' => $this->lower_limit === null ? null : (float) $this->lower_limit,
            'upperLimit' => $this->upper_limit === null ? null : (float) $this->upper_limit,
            'limitLabel' => $this->limit_label,
            'differenceLabel' => $this->difference_label,
            'status' => [
                'value' => $status,
                'label' => AnalysisElementStatus::label($status),
                'tone' => AnalysisElementStatus::tone($status),
            ],
            'validityReason' => $this->validity_reason,
            'measuredAt' => $this->measured_at?->toIso8601String(),
        ];
    }
}
