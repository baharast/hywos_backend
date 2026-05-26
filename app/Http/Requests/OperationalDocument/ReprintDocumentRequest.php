<?php

namespace App\Http\Requests\OperationalDocument;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Reprint an operational document (V1.2 §13 — Reprint Document, §18 Reprint
 * rule).
 *
 * `reason` is mandatory — reprint is an auditable action, the reason is
 * stored on both the new print attempt row and the audit log. The original
 * generation/print history is preserved on prior attempts.
 */
class ReprintDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => 'required|string|min:3|max:5000',
            'printer_id' => 'nullable|string|max:36',
            'printer_name' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'A reprint reason is required.',
            'reason.min' => 'Reprint reason must be at least 3 characters.',
        ];
    }
}
