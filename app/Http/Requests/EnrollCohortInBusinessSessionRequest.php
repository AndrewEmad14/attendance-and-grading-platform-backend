<?php

namespace App\Http\Requests;

use App\Models\BusinessSession;
use App\Models\Cohort;
use Illuminate\Foundation\Http\FormRequest;

class EnrollCohortInBusinessSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $businessSession = $this->route('businessSession');
        $cohortId = $this->input('cohort_id');

        $targetCohort = $cohortId ? Cohort::find($cohortId) : null;

        if (! $businessSession instanceof BusinessSession) {
            return false;
        }

        // Pass the business session instance and the cohort model context to the policy
        return $this->user()->can('enrollCohort', [$businessSession, $targetCohort]);
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
            },
        ];
    }
}
