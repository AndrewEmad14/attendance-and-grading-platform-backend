<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        $track = $this->route('track');
        return $this->user()->can('update', $track);
    }

    public function rules(): array
    {
        $track = $this->route('track');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tracks', 'name')->ignore($track?->id),
            ],
        ];
    }
}