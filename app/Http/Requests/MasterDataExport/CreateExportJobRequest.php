<?php

namespace App\Http\Requests\MasterDataExport;

use App\Enums\ExportCategory;
use App\Enums\ExportFieldSet;
use App\Enums\ExportFormat;
use App\Enums\ExportRecordScope;
use App\Enums\ExportStatusScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateExportJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string', Rule::in(ExportCategory::all())],

            'record_scope' => ['required', 'string', Rule::in(ExportRecordScope::all())],
            'date_from' => ['nullable', 'date', 'required_if:record_scope,' . ExportRecordScope::CREATED_OR_UPDATED_IN_RANGE],
            'date_to'   => ['nullable', 'date', 'required_if:record_scope,' . ExportRecordScope::CREATED_OR_UPDATED_IN_RANGE, 'after_or_equal:date_from'],

            'status_scope' => ['required', 'string', Rule::in(ExportStatusScope::all())],
            'field_set'    => ['required', 'string', Rule::in(ExportFieldSet::all())],
            'format'       => ['required', 'string', Rule::in(ExportFormat::all())],

            'requested_by_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
