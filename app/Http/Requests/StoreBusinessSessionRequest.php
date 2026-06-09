<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'track_admin' || $this->user()->role === 'branch_manager';
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],
        ];
    }
}