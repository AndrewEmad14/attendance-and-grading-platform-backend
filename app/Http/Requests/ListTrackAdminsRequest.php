<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListTrackAdminsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'string', 'max:255'],
            'cohort'    => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'track_id'  => ['sometimes', 'integer', 'min:1', 'exists:tracks,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort'      => ['sometimes', 'string', 'in:name,-name'],
            'page'      => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'cohort.max'      => 'cohort cannot exceed 10,000.',
            'page.max'        => 'page cannot exceed 1000.',
            'name.max'        => 'name cannot exceed 255 characters.',
            'cohort.min'      => 'cohort cannot be negative.',
            'track_id.min'    => 'track_id cannot be negative.',
            'page.min'        => 'page cannot be negative.',
            'track_id.exists' => 'The selected track does not exist.',
            'sort.in'         => 'Invalid sort value. Allowed: name, -name.',
        ];
    }
}