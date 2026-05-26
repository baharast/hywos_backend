<?php

namespace App\Http\Requests\ProductSpecification;

use App\Enums\AnalysisTypeApplicable;
use App\Enums\CertificateMapping;
use App\Enums\ProductSpecStatus;
use App\Models\ProductSpecification;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Edit one gas-limit row (V2.1 §4.4 + §7).
 *
 * `component` is INTENTIONALLY not in the rule set — changing the
 * component on an existing row is forbidden by V2.1 §4.5 ("Do not allow
 * editing multiple gas rows at once" / one row per component). The
 * service ignores any submitted `component` field defensively.
 *
 * `reason` is REQUIRED when the parent spec is `active` (existing-value
 * edit, V2.1 §7).
 */
class UpdateGasLimitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit' => 'sometimes|string|max:20',
            'lower_limit' => 'sometimes|nullable|numeric',
            'upper_limit' => 'sometimes|nullable|numeric',
            'warning_limit' => 'sometimes|nullable|numeric',
            'critical_limit' => 'sometimes|nullable|numeric',
            'precision_decimals' => 'sometimes|nullable|integer|min:0|max:9',
            'rounding_rule' => 'sometimes|nullable|in:round,truncate,banker',
            'applies_to_analysis_types' => 'sometimes|array|min:1',
            'applies_to_analysis_types.*' => 'in:' . implode(',', AnalysisTypeApplicable::all()),
            'required_for_release' => 'sometimes|boolean',
            'certificate_mapping' => 'sometimes|in:' . implode(',', CertificateMapping::all()),
            'display_order' => 'sometimes|integer|min:0|max:65535',
            'reason' => $this->reasonRule(),
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            if ($this->has('lower_limit') || $this->has('upper_limit')) {
                $low = $this->input('lower_limit');
                $high = $this->input('upper_limit');
                if (! is_null($low) && ! is_null($high) && (float) $low > (float) $high) {
                    $v->errors()->add('upper_limit', 'upper_limit must be greater than lower_limit.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A change reason is required when editing a row on an active specification.',
        ];
    }
}
