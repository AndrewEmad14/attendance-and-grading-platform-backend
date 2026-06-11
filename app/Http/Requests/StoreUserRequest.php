<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Enums\Role;
use App\Enums\CompensationType;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isStudent  = $this->input('role') === Role::STUDENT;
        $isStaff    = in_array($this->input('role'), [
            Role::INSTRUCTOR,
            Role::TRACK_ADMIN,
            Role::BRANCH_MANAGER,
        ]);
        $isInternal        = $this->input('compensation_type') === CompensationType::INTERNAL->value;
        $compensationTypes = implode(',', array_column(CompensationType::cases(), 'value'));
        $roles             = implode(',', [
            Role::STUDENT,
            Role::INSTRUCTOR,
            Role::TRACK_ADMIN,
            Role::BRANCH_MANAGER,
        ]);

        return [
            // common fields
            'name'       => ['required', 'string', 'min:3', 'max:255'],
            'email'      => ['required', 'string', 'email:rfc,dns', 'unique:users,email', 'max:255'],
            'role'       => ['required', 'string', "in:$roles"],
            'expires_at' => ['required', 'date', 'after:today'],

            // student only fields
            'cohort_id'    => [$isStudent ? 'required' : 'prohibited', 'integer', 'min:1', 'max:100000', 'exists:cohorts,id'],
            'lab_group_id' => [$isStudent ? 'required' : 'prohibited', 'integer', 'min:1', 'max:100000', 'exists:lab_groups,id'],

            // staff only fields
            'compensation_type' => [$isStaff ? 'required' : 'prohibited', 'string', "in:$compensationTypes"],
            'hourly_rate'       => [$isStaff ? 'required' : 'prohibited', 'integer', 'min:0', 'max:100000'],
            'fixed_salary'      => [$isStaff && $isInternal ? 'required' : 'prohibited', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            // name
            'name.required' => 'Name is required.',
            'name.string'   => 'Name must be a string.',
            'name.min'      => 'Name must be at least 3 characters.',
            'name.max'      => 'Name cannot exceed 255 characters.',

            // email
            'email.required' => 'Email is required.',
            'email.email'    => 'Email must be a valid email address with a real domain.',
            'email.unique'   => 'This email is already taken.',
            'email.max'      => 'Email cannot exceed 255 characters.',

            // role
            'role.required' => 'Role is required.',
            'role.in'       => 'Invalid role. Allowed: student, instructor, track_admin, branch_manager.',

            // expires_at
            'expires_at.required' => 'Expiry date is required.',
            'expires_at.date'     => 'Expiry date must be a valid date.',
            'expires_at.after'    => 'Expiry date must be in the future.',

            // cohort_id
            'cohort_id.required'   => 'cohort_id is required for students.',
            'cohort_id.prohibited' => 'cohort_id is only allowed for students.',
            'cohort_id.integer'    => 'cohort_id must be an integer.',
            'cohort_id.min'        => 'cohort_id must be at least 1.',
            'cohort_id.max'        => 'cohort_id cannot exceed 100,000.',
            'cohort_id.exists'     => 'The selected cohort does not exist.',

            // lab_group_id
            'lab_group_id.required'   => 'lab_group_id is required for students.',
            'lab_group_id.prohibited' => 'lab_group_id is only allowed for students.',
            'lab_group_id.integer'    => 'lab_group_id must be an integer.',
            'lab_group_id.min'        => 'lab_group_id must be at least 1.',
            'lab_group_id.max'        => 'lab_group_id cannot exceed 100,000.',
            'lab_group_id.exists'     => 'The selected lab group does not exist.',

            // compensation_type
            'compensation_type.required'   => 'compensation_type is required for staff.',
            'compensation_type.prohibited' => 'compensation_type is only allowed for staff.',
            'compensation_type.in'         => 'Invalid compensation type. Allowed: internal, external.',

            // hourly_rate
            'hourly_rate.required'   => 'hourly_rate is required for staff.',
            'hourly_rate.prohibited' => 'hourly_rate is only allowed for staff.',
            'hourly_rate.integer'    => 'hourly_rate must be an integer.',
            'hourly_rate.min'        => 'hourly_rate cannot be negative.',
            'hourly_rate.max'        => 'hourly_rate cannot exceed 100,000.',

            // fixed_salary
            'fixed_salary.required'   => 'fixed_salary is required for internal staff.',
            'fixed_salary.prohibited' => 'fixed_salary is only allowed for internal staff.',
            'fixed_salary.integer'    => 'fixed_salary must be an integer.',
            'fixed_salary.min'        => 'fixed_salary cannot be negative.',
            'fixed_salary.max'        => 'fixed_salary cannot exceed 1,000,000.',
        ];
    }
}