<?php

namespace App\Http\Requests;

use App\Models\Cohort;
use App\Models\Track;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class StoreCohortRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Cohort::class);
    }

    public function rules(): array
    {
        return [
            'number' => [
                'required',
                'integer',
                'min:1',
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function () {
                $track = $this->route('track');

                if ($track instanceof Track) {
                    $number = $this->input('number');

                    $duplicateExists = Cohort::where('track_id', $track->id)
                        ->where('number', $number)
                        ->exists();

                    if ($duplicateExists) {
                        $this->validator->errors()->add(
                            'number',
                            'A cohort with this number already exists for the specified track.'
                        );
                    }

                    $activeExists = Cohort::where('track_id', $track->id)
                        ->where('is_active', true)
                        ->exists();

                    if ($activeExists) {
                        $this->validator->errors()->add(
                            'track_id', 
                            'The selected track currently has an active cohort.'
                        );
                    }
                }
            }
        ];
    }
}
