<?php

namespace App\Http\Requests\ClarificationCase;

use App\Enums\BlockingImpact;
use App\Enums\ClarificationEntityType;
use App\Enums\ClarificationPrimaryActionType;
use App\Enums\ClarificationSeverity;
use App\Enums\ClarificationSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateClarificationCaseRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            // V1.3 §5 — source is required at create time so the FE can
            // immediately render the correct source label and the controller
            // can derive the typical owner role / primary action.
            'source' => ['required', 'string', Rule::in(ClarificationSource::all())],

            'category' => 'nullable|string|max:40',
            'description' => 'required|string|max:5000',
            'entity_type' => ['required', 'string', Rule::in(ClarificationEntityType::all())],
            'entity_id' => 'required|string|max:36',
            'entity_label' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'reason_code' => 'nullable|string|max:100',
            'severity' => ['nullable', 'string', Rule::in(ClarificationSeverity::all())],

            // V1.3 §4.2 — defaults to `none` in the model boot hook if
            // omitted.
            'blocking_impact' => ['nullable', 'string', Rule::in(BlockingImpact::all())],

            // V1.3 §8 — primary action is normally derived at read time, but
            // the creating module may pin it (e.g. plant_visit /raise-clarification
            // already knows the right action).
            'primary_action' => ['nullable', 'string', Rule::in(ClarificationPrimaryActionType::all())],
            'action_needed' => 'nullable|string|max:255',

            'owner_role' => 'nullable|string|max:50',
            'is_blocking' => 'nullable|boolean',
            'related_plant_visit_id' => 'nullable|string|size:36',
            'related_order_id' => 'nullable|string|size:36',
            'related_driver_id' => 'nullable|string|size:36',
            'related_trailer_id' => 'nullable|string|size:36',
            'correlation_id' => 'nullable|string|max:64',
        ];
    }
}
