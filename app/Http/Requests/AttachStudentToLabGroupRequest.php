<?php

namespace App\Http\Requests;

use App\Models\LabGroup;
use App\Models\StudentProfile;
use Illuminate\Foundation\Http\FormRequest;

class AttachStudentToLabGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'track_admin' || $this->user()->role === 'branch_manager';
    }

    public function rules(): array
    {
        return [
            'student_id' => [
                'required',
                'integer',
                'exists:student_profiles,id',
            ],
        ];  
    }

    public function after(): array
    {
        return [
            function () {
                $labGroup = $this->route('labGroup');
                $studentId = $this->input('student_id');

                if ($labGroup instanceof LabGroup && $studentId) {
                    $student = StudentProfile::find($studentId);

                    if ($student && $student->cohort_id !== $labGroup->cohort_id) {
                        $this->validator->errors()->add(
                            'student_id',
                            'The selected student profile does not belong to this lab group\'s parent cohort.'
                        );
                    }

                    $alreadyAttached = $student && $student->lab_group_id === $labGroup->id;
                    if ($alreadyAttached) {
                        $this->validator->errors()->add(
                            'student_id',
                            'The designated student is already registered to this lab group.'
                        );
                    }
                }
            }
        ];
    }
}