<?php

namespace App\Http\Resources;

use App\Enums\AnalysisTypeApplicable;
use App\Enums\CertificateMapping;
use App\Enums\GasComponent;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductGasLimitResource extends JsonResource
{
    public function toArray($request): array
    {
        $component = $this->component;
        $appliesTo = is_array($this->applies_to_analysis_types)
            ? $this->applies_to_analysis_types
            : [];

        return [
            'id' => $this->id,
            'specId' => $this->spec_id,
            'component' => $component === null ? null : [
                'value' => $component,
                'label' => GasComponent::label($component),
                'defaultLimitDirection' => GasComponent::defaultLimitDirection($component),
            ],
            'unit' => $this->unit,
            'lowerLimit' => $this->lower_limit === null ? null : (float) $this->lower_limit,
            'upperLimit' => $this->upper_limit === null ? null : (float) $this->upper_limit,
            'warningLimit' => $this->warning_limit === null ? null : (float) $this->warning_limit,
            'criticalLimit' => $this->critical_limit === null ? null : (float) $this->critical_limit,
            'precisionDecimals' => $this->precision_decimals,
            'roundingRule' => $this->rounding_rule,
            'appliesToAnalysisTypes' => array_map(fn ($t) => [
                'value' => $t,
                'label' => AnalysisTypeApplicable::label($t),
            ], $appliesTo),
            'requiredForRelease' => (bool) $this->required_for_release,
            'certificateMapping' => $this->certificate_mapping === null ? null : [
                'value' => $this->certificate_mapping,
                'label' => CertificateMapping::label($this->certificate_mapping),
            ],
            'displayOrder' => (int) $this->display_order,
            'lastChangeReason' => $this->last_change_reason,
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
