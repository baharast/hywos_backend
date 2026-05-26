<?php

namespace App\Http\Resources;

use App\Enums\CalibrationRunResult;
use App\Enums\GasComponent;
use Illuminate\Http\Resources\Json\JsonResource;

class CalibrationComponentResource extends JsonResource
{
    public function toArray($request): array
    {
        $component = $this->component;

        return [
            'id' => $this->id,
            'profileId' => $this->profile_id,
            'component' => $component === null ? null : [
                'value' => $component,
                'label' => GasComponent::label($component),
            ],
            'unit' => $this->unit,

            // Editable configuration
            'exactValue' => $this->exact_value === null ? null : (float) $this->exact_value,
            'toleranceAbs' => $this->tolerance_abs === null ? null : (float) $this->tolerance_abs,
            'tolerancePercent' => $this->tolerance_percent === null ? null : (float) $this->tolerance_percent,
            'precisionDecimals' => $this->precision_decimals,
            'roundingRule' => $this->rounding_rule,

            // Read-only — set by calibration run, NEVER edited by user (V2.1 §5.7)
            'lastMeasuredValue' => $this->last_measured_value === null ? null : (float) $this->last_measured_value,
            'lastDeviation' => $this->last_deviation === null ? null : (float) $this->last_deviation,
            'lastDeviationPercent' => $this->last_deviation_percent === null ? null : (float) $this->last_deviation_percent,
            'lastResult' => $this->last_result === null ? null : [
                'value' => $this->last_result,
                'label' => CalibrationRunResult::label($this->last_result),
                'tone' => CalibrationRunResult::tone($this->last_result),
            ],
            'lastRunAt' => $this->last_run_at?->toIso8601String(),

            'lastChangeReason' => $this->last_change_reason,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
