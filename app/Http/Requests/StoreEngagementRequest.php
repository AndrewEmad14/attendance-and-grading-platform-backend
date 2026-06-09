<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Course;
use App\Models\Lab;
use App\Models\BusinessSession;

class StoreEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->role === 'track_admin' || $this->user()->role === 'branch_manager';
    }

    protected function prepareForValidation(): void
    {
        $morphMap = [
            'lecture'          => Course::class,
            'lab'              => Lab::class,
            'business_session' => BusinessSession::class,
        ];

        $type = $this->input('type');

        if (isset($morphMap[$type])) {
            $this->merge([
                'engageable_type' => $morphMap[$type],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'string',
                Rule::in(['lecture', 'lab', 'business_session']),
            ],
            'engageable_id' => [
                'required',
                'integer',
            ],
            'engageable_type' => [
                'required',
                'string',
                Rule::in([Course::class, Lab::class, BusinessSession::class]),
            ],
            'staff_id' => [
                'required',
                'integer',
                'exists:staff_profiles,id',
            ],
            'starts_at' => [
                'required',
                'date',
            ],
            'ends_at' => [
                'required',
                'date',
                'after:starts_at',
            ],
            'scheduled_hours' => [
                'required',
                'numeric',
                'min:0.5',
                'max:24.0',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function () {
                $type = $this->input('type');
                $engageableType = $this->input('engageable_type');
                $engageableId = $this->input('engageable_id');

                if ($engageableType && $engageableId) {
                    $exists = $engageableType::where('id', $engageableId)->exists();

                    if (!$exists) {
                        $this->validator->errors()->add(
                            'engageable_id',
                            sprintf('The selected engageable ID is invalid for the specified engagement type of %s.', $type)
                        );
                    }
                }
            }
        ];
    }
}