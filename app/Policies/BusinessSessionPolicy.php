<?php

namespace App\Policies;

use App\Models\BusinessSession;
use App\Models\Cohort;
use App\Models\User;

class BusinessSessionPolicy
{
    /**
     * Determine whether the user can enroll a cohort into a business session.
     */
    public function enrollCohort(User $user, BusinessSession $businessSession, ?Cohort $targetCohort = null): bool
    {
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin' && $targetCohort) {
            // Verify relationship boundaries explicitly against the pivot column data
            return $user->staffProfile && $user->staffProfile->managedCohorts()
                ->where('cohorts_admins.cohort_id', $targetCohort->id)
                ->exists();
        }

        return false;
    }
}
