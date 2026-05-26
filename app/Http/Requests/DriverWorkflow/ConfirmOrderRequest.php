<?php

namespace App\Http\Requests\DriverWorkflow;

use App\Services\DriverWorkflow\DriverWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * V6 §8.7 — Confirm selected order. The chosen orderId must belong to
 * the driver's eligible list for the given task; eligibility is checked
 * by the service after validation passes.
 *
 * `task` is required here because the eligible-order list is task-scoped
 * and the controller routes the follow-up step based on it.
 */
class ConfirmOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'orderId' => ['required', 'string', 'size:36'],
            'task' => ['required', 'string', Rule::in(DriverWorkflowService::TASKS_NEEDING_ORDER)],
        ];
    }

    public function messages(): array
    {
        return [
            'task.in' => 'task must be one of: filling, pickup, print_exit (only tasks that pick an order).',
        ];
    }
}
