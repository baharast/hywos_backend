<?php

namespace App\Http\Requests\ProductSpecification;

use App\Enums\ProductSpecStatus;
use App\Models\ProductSpecification;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Update metadata on an existing Product Specification (V2.1 §7).
 *
 * Reason is REQUIRED when the spec is `active` (existing-value edit);
 * optional while it is still `draft`. The controller has already
 * resolved the spec, so this request looks it up via the route param
 * and applies the right rule.
 */
class UpdateProductSpecificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $reasonRule = $this->reasonRule();

        return [
            'display_name' => 'sometimes|string|max:255',
            'quality_class' => 'sometimes|string|max:50',
            'effective_from' => 'sometimes|nullable|date',
            'effective_to' => 'sometimes|nullable|date|after:effective_from',
            'notes' => 'sometimes|nullable|string|max:5000',
            'reason' => $reasonRule,
        ];
    }

    protected function reasonRule(): string
    {
        $spec = $this->route('id')
            ? ProductSpecification::find($this->route('id'))
            : null;

        if ($spec && ProductSpecStatus::requiresReasonForEdit($spec->status)) {
            return 'required|string|min:3|max:5000';
        }
        return 'nullable|string|max:5000';
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A change reason is required when editing an active specification.',
            'reason.min' => 'Change reason must be at least 3 characters.',
        ];
    }
}
