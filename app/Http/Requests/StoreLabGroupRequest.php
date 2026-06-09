<?php

namespace App\Http\Requests;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\LabGroup;
use Illuminate\Foundation\Http\FormRequest;

class StoreLabGroupRequest extends FormRequest
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

    public function after(): array
    {
        return [
            function () {
                $cohort = $this->route('cohort');

                if ($cohort instanceof Cohort) {

                    $coursesExist = Course::where('cohort_id', $cohort->id)->exists();

                    if ($coursesExist) {
                        $this->validator->errors()->add(
                            'cohort_id',
                            'Cannot create new lab groups once courses have been configured for this cohort.'
                        );
                        return;
                    }

                    $nameExists = LabGroup::where('cohort_id', $cohort->id)
                        ->where('name', $this->input('name'))
                        ->exists();

                    if ($nameExists) {
                        $this->validator->errors()->add(
                            'name',
                            'A lab group with this designation already exists within the target cohort.'
                        );
                    }
                }
            }
        ];
    }
}