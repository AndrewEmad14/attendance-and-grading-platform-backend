<?php

namespace App\Http\Requests;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\Lab;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $type = $this->input('type');
        $engageableId = $this->input('engageable_id');

        $morphMap = [
            'lecture' => Course::class,
            'lab' => Lab::class,
            'business_session' => BusinessSession::class,
        ];

        $engageableType = $morphMap[$type] ?? null;
        $contextEntity = ($engageableType && $engageableId)
            ? $engageableType::find($engageableId)
            : null;

        // Pass the matching course, lab, or business session entity directly to the policy
        return $this->user()->can('create', [Engagement::class, $contextEntity]);
    }

    protected function prepareForValidation(): void
    {
        $morphMap = [
            'lecture' => Course::class,
            'lab' => Lab::class,
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
                    $entity = $engageableType::find($engageableId);

                    if (! $entity) {
                        $this->validator->errors()->add('engageable_id', 'The selected entity does not exist.');

                        return;
                    }

                    // Ensure the item belongs to the current scope
                    if (method_exists($entity, 'cohort') && $entity->cohort_id !== $currentTargetCohortId) {
                        $this->validator->errors()->add('engageable_id', 'The selected course/lab belongs to an alternate track cohort configuration.');
                    }
                }
            },
        ];
    }
}
