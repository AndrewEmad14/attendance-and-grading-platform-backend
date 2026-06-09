<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Engagement;
use App\Models\Course;
use App\Models\Lab;
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
                return $user->staffProfile && $user->staffProfile->managedCohorts->contains($cohortId);
            }

            // Fallback authorization for multi-track cross-boundary Business Sessions
            if ($contextEntity instanceof \App\Models\BusinessSession) {
                return true; 
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
            // Eager load relationships to optimize context validation
            $engagement->loadMissing('engageable');
            $cohortId = $this->resolveCohortIdFromEntity($engagement->engageable);

            if ($cohortId) {
                return $user->staffProfile && $user->staffProfile->managedCohorts->contains($cohortId);
            }

            if ($engagement->type === 'business_session') {
                return true;
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
            // Load missing lab group container recursively to identify parent track ownership
            return $entity->labGroup?->cohort_id;
        }

        return null;
    }
}