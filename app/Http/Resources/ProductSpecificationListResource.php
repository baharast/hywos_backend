<?php

namespace App\Http\Resources;

use App\Enums\GasComponent;
use App\Enums\ProductSpecStatus;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Slim list-row representation for the Product Specifications table
 * (V2.1 §4.1). Carries the completeness count so the FE can render the
 * "6/6 configured" / "4/6 configured" indicator without a second fetch.
 *
 * The completeness count is computed from the relation when it's been
 * eager-loaded; otherwise the resource leaves it null and the FE may
 * fall back to "unknown".
 */
class ProductSpecificationListResource extends JsonResource
{
    public function toArray($request): array
    {
        $configured = null;
        if ($this->relationLoaded('gasLimits')) {
            $configured = $this->gasLimits->count();
        }

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
            'componentCompleteness' => [
                'configured' => $configured,
                'required' => count(GasComponent::all()),
                'complete' => $configured === null ? null : ($configured >= count(GasComponent::all())),
            ],
            'effectiveFrom' => $this->effective_from?->toIso8601String(),
            'effectiveTo' => $this->effective_to?->toIso8601String(),
            'isEditable' => ProductSpecStatus::isEditable($this->status),
            'requiresReasonForEdit' => ProductSpecStatus::requiresReasonForEdit($this->status),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }
}
