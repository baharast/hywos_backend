<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;

class ExportReportRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'format' => 'nullable|string|in:pdf,xlsx,json',
            'filters' => 'nullable|array',
            'range_preset' => 'nullable|string|max:30',
            'range_from' => 'nullable|date',
            'range_to' => 'nullable|date|after_or_equal:range_from',
        ];
    }
}
