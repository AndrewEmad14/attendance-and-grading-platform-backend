<?php

namespace App\Http\Requests;

use App\Models\Cohort;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cohort = $this->route('cohort');
        return $cohort instanceof Cohort && $this->user()->can('update', $cohort);
    }

    public function rules(): array
    {
        return [
            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function () {
                $cohort = $this->route('cohort');

                if ($cohort instanceof Cohort && $this->input('is_active') === true) {
                    $activeExists = Cohort::where('track_id', $cohort->track_id)
                        ->where('id', '!=', $cohort->id)
                        ->where('is_active', true)
                        ->exists();

                    if ($activeExists) {
                        $this->validator->errors()->add(
                            'is_active',
                            'The associated track already contains an alternate active cohort.'
                        );
                    }
                }
            }
        ];
    }
}
