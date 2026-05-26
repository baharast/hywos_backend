<?php

namespace App\Http\Requests\SafetyTraining;

use Illuminate\Foundation\Http\FormRequest;

/**
 * V6 §7.6 — body shape `{ answers: [{questionId, choice}, ...] }`.
 *
 * We accept and grade ANY length array (the service tolerates missing /
 * unknown question ids — they just don't earn points). That keeps the
 * grading honest even if the FE under-submits, and avoids leaking the
 * exam length through validation.
 */
class SubmitExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array|min:1|max:50',
            'answers.*.questionId' => 'required|string|max:10',
            'answers.*.choice' => 'required|string|max:5',
        ];
    }
}
