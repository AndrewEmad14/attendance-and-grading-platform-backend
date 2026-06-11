<?php
// app/Http/Requests/Api/ResetPasswordRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'string', 'email:rfc,dns', 'max:255', 'exists:users,email'],
            'password'              => ['required', 'string', 'min:8', 'max:64', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            // token
            'token.required' => 'Reset token is required.',
            'token.string'   => 'Reset token must be a string.',

            // email
            'email.required' => 'Email is required.',
            'email.email'    => 'Email must be a valid email address with a real domain.',
            'email.max'      => 'Email cannot exceed 255 characters.',
            'email.exists'   => 'No account found with this email address.',

            // password
            'password.required'  => 'Password is required.',
            'password.min'       => 'Password must be at least 8 characters.',
            'password.max'       => 'Password cannot exceed 64 characters.',
            'password.confirmed' => 'Password confirmation does not match.',

            // password_confirmation
            'password_confirmation.required' => 'Password confirmation is required.',
        ];
    }
}