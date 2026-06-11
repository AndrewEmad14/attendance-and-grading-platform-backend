<?php

// app/Http/Requests/Api/ListInstructorsRequest.php

namespace App\Http\Requests;

use App\Enums\CompensationType;
use Illuminate\Foundation\Http\FormRequest;

class ListInstructorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $compensationTypes = implode(',', array_column(CompensationType::cases(), 'value'));

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'compensation_type' => ['sometimes', 'string', "in:$compensationTypes"],
            'hourly_rate_min' => ['required_with:hourly_rate_max', 'integer', 'min:0', 'max:100000'],
            'hourly_rate_max' => ['required_with:hourly_rate_min', 'integer', 'min:0', 'max:100000', 'gte:hourly_rate_min'],
            'fixed_salary_min' => ['required_with:fixed_salary_max', 'integer', 'min:0', 'max:1000000'],
            'fixed_salary_max' => ['required_with:fixed_salary_min', 'integer', 'min:0', 'max:1000000', 'gte:fixed_salary_min'],
            'sort' => ['sometimes', 'string', 'in:name,-name,hourly_rate,-hourly_rate,fixed_salary,-fixed_salary'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'hourly_rate_min.required_with' => 'hourly_rate_min is required when hourly_rate_max is present.',
            'hourly_rate_max.required_with' => 'hourly_rate_max is required when hourly_rate_min is present.',
            'hourly_rate_max.gte' => 'hourly_rate_max must be greater than or equal to hourly_rate_min.',
            'hourly_rate_min.max' => 'hourly_rate_min cannot exceed 100,000.',
            'hourly_rate_max.max' => 'hourly_rate_max cannot exceed 100,000.',
            'fixed_salary_min.required_with' => 'fixed_salary_min is required when fixed_salary_max is present.',
            'fixed_salary_max.required_with' => 'fixed_salary_max is required when fixed_salary_min is present.',
            'fixed_salary_max.gte' => 'fixed_salary_max must be greater than or equal to fixed_salary_min.',
            'fixed_salary_min.max' => 'fixed_salary_min cannot exceed 1,000,000.',
            'fixed_salary_max.max' => 'fixed_salary_max cannot exceed 1,000,000.',
            'name.max' => 'name cannot exceed 255 characters.',
            'hourly_rate_min.min' => 'hourly_rate_min cannot be negative.',
            'hourly_rate_max.min' => 'hourly_rate_max cannot be negative.',
            'fixed_salary_min.min' => 'fixed_salary_min cannot be negative.',
            'fixed_salary_max.min' => 'fixed_salary_max cannot be negative.',
            'page.min' => 'page cannot be negative.',
            'sort.in' => 'Invalid sort value. Allowed: name, -name, hourly_rate, -hourly_rate, fixed_salary, -fixed_salary.',
            'compensation_type.in' => 'Invalid compensation type. Allowed: internal, external.',
            'page.max' => 'page cannot exceed 1000.',
        ];
    }
}
