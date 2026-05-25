<?php

namespace App\Http\Requests\PlantVisit;

use App\Enums\PlantVisitLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_location' => ['required', Rule::in(PlantVisitLocation::all())],
        ];
    }
}
