<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled via policy in controller
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:3', 'max:255'],
            'body' => ['sometimes', 'string', 'min:10', 'max:5000'],
            'cohort_id' => ['prohibited'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Title messages
            'title.string' => 'The announcement title must contain valid text characters.',
            'title.min' => 'The updated title is too short. It must be at least :min characters.',
            'title.max' => 'The updated title cannot exceed :max characters.',

            // Body messages
            'body.string' => 'The announcement content must be valid text.',
            'body.min' => 'The updated content is too short. It must be at least :min characters.',
            'body.max' => 'The updated content is too long. It cannot exceed :max characters.',

            // Safety rule message
            'cohort_id.prohibited' => 'An announcement cannot be moved to a different cohort once created.',
        ];
    }
}
