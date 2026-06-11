<?php
// app/Http/Requests/Api/UpdateUserRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\CompensationType;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled by policy
    }

    public function rules(): array
    {
        $user       = $this->route('user');
        $isStaff    = in_array($user->role, ['instructor', 'track_admin', 'branch_manager']);
        $isInternal = $this->input('compensation_type') === CompensationType::INTERNAL->value
            ?? $user->staffProfile?->compensation_type === CompensationType::INTERNAL->value;

        $compensationTypes = implode(',', array_column(CompensationType::cases(), 'value'));

        return [
            // common fields — all optional since this is a PATCH
            'name'       => ['sometimes', 'string', 'min:3', 'max:255'],
            'email'      => ['sometimes', 'string', 'email:rfc,dns', 'unique:users,email,' . $user->id, 'max:255'],
            'expires_at' => ['sometimes', 'date', 'after:today'],

            // staff only fields
            'compensation_type' => [$isStaff ? 'sometimes' : 'prohibited', 'string', "in:$compensationTypes"],
            'hourly_rate'       => [$isStaff ? 'sometimes' : 'prohibited', 'integer', 'min:0', 'max:100000'],
            'fixed_salary'      => [$isStaff && $isInternal ? 'sometimes' : 'prohibited', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            // name
            'name.string' => 'Name must be a string.',
            'name.min'    => 'Name must be at least 3 characters.',
            'name.max'    => 'Name cannot exceed 255 characters.',

            // email
            'email.email'  => 'Email must be a valid email address with a real domain.',
            'email.unique' => 'This email is already taken.',
            'email.max'    => 'Email cannot exceed 255 characters.',

            // expires_at
            'expires_at.date'  => 'Expiry date must be a valid date.',
            'expires_at.after' => 'Expiry date must be in the future.',

            // compensation_type
            'compensation_type.prohibited' => 'compensation_type is only allowed for staff.',
            'compensation_type.in'         => 'Invalid compensation type. Allowed: internal, external.',

            // hourly_rate
            'hourly_rate.prohibited' => 'hourly_rate is only allowed for staff.',
            'hourly_rate.integer'    => 'hourly_rate must be an integer.',
            'hourly_rate.min'        => 'hourly_rate cannot be negative.',
            'hourly_rate.max'        => 'hourly_rate cannot exceed 100,000.',

            // fixed_salary
            'fixed_salary.prohibited' => 'fixed_salary is only allowed for internal staff.',
            'fixed_salary.integer'    => 'fixed_salary must be an integer.',
            'fixed_salary.min'        => 'fixed_salary cannot be negative.',
            'fixed_salary.max'        => 'fixed_salary cannot exceed 1,000,000.',
        ];
    }
}