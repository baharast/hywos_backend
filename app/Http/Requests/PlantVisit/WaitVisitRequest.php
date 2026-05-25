<?php

namespace App\Http\Requests\PlantVisit;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V1.6 §2.1 — status=waiting REQUIRES a visible reason. The model saving()
 * hook would silently demote waiting→normal without one; we reject at the
 * HTTP boundary with an explicit 422 WAITING_REASON_REQUIRED.
 */
class WaitVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'waiting_reason' => 'required|string|min:3|max:255',
        ];
    }
}
