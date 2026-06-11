<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Role;

class ListStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by route middleware
    }

    public function rules(): array
    {
        return [
            'name'           => ['sometimes', 'string', 'max:255'],
            'cohort'         => ['sometimes', 'integer', 'min:1', 'max:10000'],
            'track_id'       => ['sometimes', 'integer', 'min:1', 'exists:tracks,id'],
            'is_active'      => ['sometimes', 'boolean'],
            'lab_group_id'   => ['sometimes', 'integer', 'min:0', 'exists:lab_groups,id'],
            'tag_id'         => ['sometimes', 'integer', 'min:0', 'exists:tags,id'],
            'attendance_min' => ['required_with:attendance_max', 'integer', 'min:0', 'max:1000'],
            'attendance_max' => ['required_with:attendance_min', 'integer', 'min:0', 'max:1000', 'gte:attendance_min'],
            'sort'           => ['sometimes', 'string', 'in:name,-name,cohort,-cohort,attendance,-attendance'],
            'page'           => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'attendance_min.required_with' => 'attendance_min is required when attendance_max is present.',
            'attendance_max.required_with' => 'attendance_max is required when attendance_min is present.',
            'attendance_max.gte'           => 'attendance_max must be greater than or equal to attendance_min.',
            'attendance_min.max'           => 'attendance_min cannot exceed 250.',
            'attendance_max.max'           => 'attendance_max cannot exceed 250.',
            'attendance_min.min'           => 'attendance_min cannot be negative.',
            'attendance_max.min'           => 'attendance_max cannot be negative.',
            'cohort.max'                   => 'cohort cannot exceed 10000.',
            'page.min'                     => 'page cannot be negative.',
            'cohort.min'                   => 'cohort cannot be negative.',
            'name.max'                     => 'name cannot exceed 255 characters.',
            'page.max'                     => 'page cannot exceed 1000.',
            'sort.in'                      => 'Invalid sort value. Allowed: name, -name, cohort, -cohort, attendance, -attendance.',
            'track_id.exists'              => 'The selected track does not exist.',
            'lab_group_id.exists'          => 'The selected lab group does not exist.',
            'tag_id.exists'                => 'The selected tag does not exist.',
        ];
    }
}