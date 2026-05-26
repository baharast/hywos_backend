<?php

namespace App\Http\Requests\DriverWorkflow;

use App\Services\DriverWorkflow\DriverWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V6 §8.5 — Task selection. Only the 5 documented tasks are accepted.
 * The whitelist lives on the service so the controller and form request
 * stay in sync.
 */
class SelectTaskRequest extends FormRequest
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

    public function messages(): array
    {
        return [
            'task.in' => 'task must be one of: filling, pickup, parking, print_exit, exit.',
        ];
    }
}
