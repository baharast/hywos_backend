<?php

namespace App\Http\Requests\PlantVisit;

use App\Enums\PlantVisitStep;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvanceStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_step' => ['required', Rule::in(PlantVisitStep::all())],
        ];
    }
}
