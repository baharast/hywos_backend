<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $companyId = $this->route('id');

        return [
            'code' => "required|string|max:50|unique:companies,code,{$companyId},id",
            'name' => 'required|string|max:150',
            'legal_name' => 'nullable|string|max:150',
            'description' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ];
    }
}
