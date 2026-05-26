<?php

namespace App\Http\Requests\ProductSpecification;

use App\Enums\AnalysisTypeApplicable;
use App\Enums\CertificateMapping;
use App\Enums\GasComponent;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add one gas-limit row to a Product Specification (V2.1 §4.4).
 *
 * One component per request — adding the same component twice for the
 * same spec is rejected by the service with 409
 * PRODUCT_SPEC_GAS_LIMIT_EXISTS (the unique key would 500 otherwise).
 *
 * V2.1 §9: at least ONE of lower_limit / upper_limit must be present.
 * Enforced in `withValidator()` below.
 */
class AddGasLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'component' => 'required|in:' . implode(',', GasComponent::all()),
            'unit' => 'required|string|max:20',
            'lower_limit' => 'nullable|numeric',
            'upper_limit' => 'nullable|numeric',
            'warning_limit' => 'nullable|numeric',
            'critical_limit' => 'nullable|numeric',
            'precision_decimals' => 'nullable|integer|min:0|max:9',
            'rounding_rule' => 'nullable|in:round,truncate,banker',
            'applies_to_analysis_types' => 'required|array|min:1',
            'applies_to_analysis_types.*' => 'in:' . implode(',', AnalysisTypeApplicable::all()),
            'required_for_release' => 'sometimes|boolean',
            'certificate_mapping' => 'required|in:' . implode(',', CertificateMapping::all()),
            'display_order' => 'nullable|integer|min:0|max:65535',
            'reason' => 'nullable|string|max:5000',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $low = $this->input('lower_limit');
            $high = $this->input('upper_limit');
            if (is_null($low) && is_null($high)) {
                $v->errors()->add(
                    'lower_limit',
                    'At least one of lower_limit or upper_limit must be set (V2.1 §9).'
                );
            }
            if (! is_null($low) && ! is_null($high) && (float) $low > (float) $high) {
                $v->errors()->add('upper_limit', 'upper_limit must be greater than lower_limit.');
            }
        });
    }
}
