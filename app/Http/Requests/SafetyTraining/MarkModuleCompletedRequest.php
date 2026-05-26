<?php

namespace App\Http\Requests\SafetyTraining;

use App\Services\SafetyTraining\SafetyTrainingCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkModuleCompletedRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Driver session-id is the credential (validated by the controller
        // against terminal_sessions). No Sanctum/permission check applies
        // to this public terminal endpoint.
        return true;
    }

    public function rules(): array
    {
        return [
            'moduleId' => ['required', 'string', Rule::in(SafetyTrainingCatalog::moduleIds())],
        ];
    }

    public function messages(): array
    {
        return [
            'moduleId.in' => 'moduleId must be one of: ' . implode(', ', SafetyTrainingCatalog::moduleIds()),
        ];
    }
}
