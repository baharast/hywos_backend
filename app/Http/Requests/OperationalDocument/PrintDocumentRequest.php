<?php

namespace App\Http\Requests\OperationalDocument;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Print an operational document (V1.2 §13 — Print Document).
 *
 * `printer_id` is optional in MVP — when omitted the backend may route to
 * a default/auto-assigned printer. `printer_name` is the human-readable
 * label persisted on the document + print attempt rows.
 */
class PrintDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'printer_id' => 'nullable|string|max:36',
            'printer_name' => 'nullable|string|max:100',
        ];
    }
}
