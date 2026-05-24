<?php

namespace App\Http\Requests\ChipCard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AssignChipCardRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'entity_type' => 'required|string|in:driver,trailer',
            'entity_id' => 'required|string|size:36',
            'reason' => 'required|string|min:3|max:1000',
            'reason_code' => 'nullable|string|max:100',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $type = $this->input('entity_type');
            $id = $this->input('entity_id');
            if (! $type || ! $id) {
                return;
            }
            $table = $type === 'driver' ? 'drivers' : 'trailers';
            $exists = \Illuminate\Support\Facades\DB::table($table)->where('id', $id)->exists();
            if (! $exists) {
                $v->errors()->add('entity_id', "Referenced {$type} does not exist.");
            }
        });
    }
}
