<?php

namespace App\Http\Requests\OperationalDocument;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Mark an operational document as handed over (V1.2 §13 — Mark as Handed
 * Over).
 *
 * `note` is optional — backend policy may make it mandatory per document
 * type in a later revision, but the spec only requires it where backend
 * policy demands.
 */
class HandOverDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'note' => 'nullable|string|max:5000',
        ];
    }
}
