<?php

namespace App\Http\Requests;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\Lab;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEngagementRequest extends FormRequest
{
    // A memory buffer property to store the resolved model entity across request lifecycle phases
    protected ?Model $contextEntity = null;

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

        // Cache the model instance directly in the request property so we don't run duplicate SQL queries
        if ($engageableType && $engageableId) {
            $this->contextEntity = $engageableType::find($engageableId);
        }

        return $this->user()->can('create', [Engagement::class, $this->contextEntity]);
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
                // Reuse the entity instance parsed during the authorize phase
                $entity = $this->contextEntity;

                if (! $entity) {
                    $this->validator->errors()->add('engageable_id', 'The selected entity does not exist.');

                    return;
                }

                $user = $this->user();

                // Track Admins must be verified against territory allocations
                if ($user->role === 'track_admin') {
                    if ($entity instanceof BusinessSession) {
                        // Check if the Track Admin manages at least one cohort enrolled in this session
                        $managedCohortIds = $user->staffProfile->managedCohorts()->pluck('cohorts_admins.cohort_id')->toArray();
                        $isEnrolled = $entity->cohorts()->whereIn('cohorts.id', $managedCohortIds)->exists();

                        if (! $isEnrolled) {
                            $this->validator->errors()->add('engageable_id', 'Your managed cohorts must be enrolled in this business session before you can schedule it.');
                        }
                    } else {
                        // Safely resolve the target cohort ID for standard Course/Lab models
                        $cohortId = null;
                        if ($entity instanceof Course) {
                            $cohortId = $entity->cohort_id;
                        } elseif ($entity instanceof Lab) {
                            $cohortId = $entity->labGroup?->cohort_id;
                        }

                        // Verify that this cohort ID exists inside the admin's territory
                        $hasTerritoryAccess = $cohortId && $user->staffProfile->managedCohorts()
                            ->where('cohorts_admins.cohort_id', $cohortId)
                            ->exists();

                        if (! $hasTerritoryAccess) {
                            $this->validator->errors()->add('engageable_id', 'The selected course/lab belongs to an alternate track cohort configuration.');
                        }
                    }
                }
            },
        ];
    }
}
