<?php

namespace App\Http\Requests\Printer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.4 §6/§10 — retry-a-failed-job. Reason is optional per spec but
 * we accept and persist it when supplied for clearer audit context.
 */
class RetryPrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'nullable|string|max:5000',
        ];
    }
}
