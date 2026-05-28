<?php

namespace App\Http\Requests\Printer;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.4 §6/§10 — reroute-to-replacement. Reason is REQUIRED so the
 * audit trail can distinguish a deliberate operator decision from a
 * silent automated retry.
 */
class ReroutePrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'replacement_printer_id' => 'required|string|max:36',
            'reason' => 'required|string|min:3|max:5000',
        ];
    }
}
