<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array //any data sent to api will be validated by these
    {
        return [
            'name'                       => 'required|string|max:255',
            'deliverables'               => 'sometimes|array',
            'deliverables.*.name'        => 'required_with:deliverables|string|max:255',
            'deliverables.*.type'        => 'required_with:deliverables|in:lab,exam,project',
            'deliverables.*.max_score'   => 'required_with:deliverables|integer|min:1',
            'deliverables.*.course_weight' => 'required_with:deliverables|integer|min:1|max:100',
            'deliverables.*.due_date'    => 'required_with:deliverables|date',
        ];
    }

    public function withValidator($validator): void
    {// ccheck if weights sum to 100

        $validator->after(function ($validator) {
            $deliverables = $this->input('deliverables', []);
            if (!empty($deliverables)) {
                $sum = array_sum(array_column($deliverables, 'course_weight'));
                if ($sum !== 100) {
                    $validator->errors()->add('deliverables', "Component weights must sum to 100. Current sum: {$sum}.");
                }
            }
        });
    }
}
