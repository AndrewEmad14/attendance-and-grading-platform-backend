<?php

namespace App\Http\Requests;

use App\Models\Engagement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $engagement = $this->route('engagement');

        if (! $engagement instanceof Engagement) {
            return false;
        }

        return $this->user()->can('update', $engagement);
    }

    public function rules(): array
    {
        return [
            'starts_at' => [
                'nullable',
                'date',
            ],
            'ends_at' => [
                'nullable',
                'date',
                'after:starts_at',
            ],
            'scheduled_hours' => [
                'nullable',
                'numeric',
                'min:0.5',
                'max:24.0',
            ],
            'staff_id' => [
                'nullable',
                'integer',
                'exists:staff_profiles,id',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function () {
                $engagement = $this->route('engagement');
                if (! $engagement instanceof Engagement) {
                    return;
                }

                $startsAt = $this->input('starts_at') ? Carbon::parse($this->input('starts_at')) : $engagement->starts_at;
                $endsAt = $this->input('ends_at') ? Carbon::parse($this->input('ends_at')) : $engagement->ends_at;

                if ($startsAt && $endsAt && $startsAt->greaterThanOrEqualTo($endsAt)) {
                    $this->validator->errors()->add(
                        'ends_at',
                        'The termination date boundary must follow the initiation date window chronologically.'
                    );
                }
            },
        ];
    }
}
