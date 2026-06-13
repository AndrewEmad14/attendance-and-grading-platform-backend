<?php

namespace App\Http\Requests;

use App\Models\Track;
use Illuminate\Foundation\Http\FormRequest;

class StoreTrackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Track::class);
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:tracks,name',
            ],
        ];
    }
}
