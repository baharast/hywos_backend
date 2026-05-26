<?php

namespace App\Http\Requests\ProductSpecification;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Create a draft Product Specification (V2.1 §4.4).
 *
 * First-time create — no `reason` required (§7). Spec starts in `draft`
 * status; gas-limit rows are added separately via
 * POST /product-specifications/{id}/gas-limits.
 */
class CreateProductSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_code' => 'required|string|max:50',
            'quality_class' => 'required|string|max:50',
            'display_name' => 'required|string|max:255',
            'spec_version' => 'nullable|string|max:20',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after:effective_from',
            'notes' => 'nullable|string|max:5000',
        ];
    }
}
