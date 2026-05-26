<?php

namespace App\Http\Requests\DriverWorkflow;

use App\Services\DriverWorkflow\DriverWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V6 §8.10 — Workflow recorded. The FE echoes back the chosen task as a
 * defensive check; if it doesn't match a known task we refuse the call
 * rather than complete with an unknown label in the audit log.
 */
class CompleteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'task' => ['required', 'string', Rule::in(DriverWorkflowService::TASKS)],
        ];
    }
}
