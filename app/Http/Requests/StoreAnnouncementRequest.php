<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled via policy in controller
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
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
            'title.required' => 'Please provide a title for the announcement.',
            'title.string' => 'The title must contain valid text characters.',
            'title.min' => 'The title is too short. It must be at least :min characters long.',
            'title.max' => 'The title cannot exceed :max characters.',

            // Body messages
            'body.required' => 'The announcement body content cannot be empty.',
            'body.string' => 'The announcement content must be valid text.',
            'body.min' => 'Please write a more detailed announcement. It must be at least :min characters.',
            'body.max' => 'The announcement content is too long. The maximum allowed limit is :max characters.',
        ];
    }
}
