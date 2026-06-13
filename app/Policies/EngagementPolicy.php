<?php

namespace App\Policies;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EngagementPolicy
{
    public function create(User $user, ?Model $contextEntity = null): bool
    {
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin' && $contextEntity) {
            $cohortId = $this->resolveCohortIdFromEntity($contextEntity);

            if ($cohortId) {
                return $user->staffProfile && $user->staffProfile->managedCohorts()->where('cohorts_admins.cohort_id', $cohortId)->exists();
            }

            // Fallback authorization check for multi-track Business Sessions
            if ($contextEntity instanceof BusinessSession) {
                $managedCohortIds = $user->staffProfile->managedCohorts()->pluck('cohorts_admins.cohort_id')->toArray();

                // Track Admin can ONLY create an engagement if at least one of their cohorts is enrolled
                return $contextEntity->cohorts()->whereIn('cohorts.id', $managedCohortIds)->exists();
            }
        }

        return false;
    }

    public function update(User $user, Engagement $engagement): bool
    {
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin') {
            $engagement->loadMissing('engageable');
            $cohortId = $this->resolveCohortIdFromEntity($engagement->engageable);

            if ($cohortId) {
                return $user->staffProfile && $user->staffProfile->managedCohorts()->where('cohorts_admins.cohort_id', $cohortId)->exists();
            }

            if ($engagement->engageable instanceof BusinessSession) {
                $managedCohortIds = $user->staffProfile->managedCohorts()->pluck('cohorts_admins.cohort_id')->toArray();

                // Track Admin can ONLY update if one of their cohorts is enrolled in this specific business session
                return $engagement->engageable->cohorts()->whereIn('cohorts.id', $managedCohortIds)->exists();
            }
        }

        return false;
    }

    private function resolveCohortIdFromEntity(?Model $entity): ?int
    {
        if ($entity instanceof Course) {
            return $entity->cohort_id;
        }

        if ($entity instanceof Lab) {
            return $entity->labGroup?->cohort_id;
        }

        return null;
    }
}
