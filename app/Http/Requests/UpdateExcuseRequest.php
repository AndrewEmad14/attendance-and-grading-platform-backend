<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExcuseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['sometimes', 'string', 'max:1000'],
            'attachment' => [
                'nullable',
                'file',
                'max:1024',
                'mimes:pdf,jpg,jpeg,png,webp',
            ],
        ];
    }
}
