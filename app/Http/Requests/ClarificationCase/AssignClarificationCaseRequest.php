<?php

namespace App\Http\Requests\ClarificationCase;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Assign / re-assign a clarification case. Per V1.3 §5 owner role is the
 * routing decision; the optional `assigned_to_user_id` pins it to a single
 * user when known.
 *
 * Owner role values come from V1.3 §14 OwnerRole enum. We Rule::in here
 * (stricter than `CreateClarificationCaseRequest` which accepts free-form
 * `owner_role`) because routing decisions are the moment we want the FE
 * picker to be canonical.
 */
class AssignClarificationCaseRequest extends FormRequest
{
    public const OWNER_ROLES = [
        'operator',
        'dispatcher_manager',
        'analysis_specialist',
        'it_support',
        'documents_reports',
        'admin',
        'system',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'owner_role' => ['required', 'string', Rule::in(self::OWNER_ROLES)],
            'assigned_to_user_id' => 'nullable|integer|min:1',
            'notes' => 'nullable|string|max:1000',
        ];
    }
}
