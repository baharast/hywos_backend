<?php

namespace App\Http\Requests\ActiveAnalysis;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Release Loading (V1.4 §5 — VA-2). Available only when pre-analysis
 * is OK AND backend has marked manual release required. Reason is
 * optional but recommended.
 */
class ReleaseLoadingRequest extends FormRequest
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
