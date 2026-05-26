<?php

namespace App\Http\Resources;

use App\Enums\GasComponent;
use App\Enums\ProductSpecStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full detail for the Selected Specification Details panel (V2.1 §4).
 * Embeds the gas-limit rows + missing-component list so the FE renders
 * the "Add row" CTA only for components that don't yet have a row.
 */
class ProductSpecificationDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $configured = $this->gasLimits->pluck('component')->all();
        $required = GasComponent::all();
        $missing = array_values(array_diff($required, $configured));

        return [
            'id' => $this->id,
            'productCode' => $this->product_code,
            'qualityClass' => $this->quality_class,
            'displayName' => $this->display_name,
            'specVersion' => $this->spec_version,
            'status' => [
                'value' => $this->status,
                'label' => ProductSpecStatus::label($this->status),
                'tone' => ProductSpecStatus::tone($this->status),
            ],
            'effectiveFrom' => $this->effective_from?->toIso8601String(),
            'effectiveTo' => $this->effective_to?->toIso8601String(),
            'notes' => $this->notes,

            'activatedAt' => $this->activated_at?->toIso8601String(),
            'activatedByUserId' => $this->activated_by_user_id,
            'retiredAt' => $this->retired_at?->toIso8601String(),
            'retiredByUserId' => $this->retired_by_user_id,

            'isEditable' => ProductSpecStatus::isEditable($this->status),
            'requiresReasonForEdit' => ProductSpecStatus::requiresReasonForEdit($this->status),

            'componentCompleteness' => [
                'configured' => count($configured),
                'required' => count($required),
                'missing' => array_map(fn ($c) => [
                    'component' => $c,
                    'label' => GasComponent::label($c),
                ], $missing),
                'complete' => count($missing) === 0,
            ],

            'gasLimits' => ProductGasLimitResource::collection(
                $this->gasLimits->sortBy('display_order')->values()
            ),

            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
