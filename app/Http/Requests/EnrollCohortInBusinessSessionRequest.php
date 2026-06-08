<?php

namespace App\Http\Requests;

use App\Models\BusinessSession;
use Illuminate\Foundation\Http\FormRequest;

class EnrollCohortInBusinessSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'track_admin' || $this->user()->role === 'branch_manager';
    }

    public function rules(): array
    {
        return [
            'cohort_id' => [
                'required',
                'integer',
                'exists:cohorts,id',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function () {
                $businessSession = $this->route('businessSession');
                $cohortId = $this->input('cohort_id');

                if ($businessSession instanceof BusinessSession && $cohortId) {
                    $alreadyEnrolled = $businessSession->cohorts()->where('cohort_id', $cohortId)->exists();

                    if ($alreadyEnrolled) {
                        $this->validator->errors()->add(
                            'cohort_id',
                            'The designated cohort is already linked to this business session.'
                        );
                    }
                }
            }
        ];
    }
}