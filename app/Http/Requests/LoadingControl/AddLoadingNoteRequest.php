<?php

namespace App\Http\Requests\LoadingControl;

use Illuminate\Foundation\Http\FormRequest;

class AddLoadingNoteRequest extends FormRequest
{
    public function authorize()
    {
        // Auth is intentionally disabled in this MVP phase.
        return true;
    }

    public function rules()
    {
        return [
            'note' => 'required|string|min:1|max:1000',
        ];
    }
}
