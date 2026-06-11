<?php

namespace App\Policies;

use App\Models\BusinessSession;
use App\Models\Cohort;
use App\Models\User;

class BusinessSessionPolicy
{
    public function enrollCohort(User $user, BusinessSession $businessSession, ?Cohort $targetCohort = null): bool
    {
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin' && $targetCohort) {
            // Enforce that Track Admins can only link cohorts assigned inside their profile boundary
            return $user->staffProfile && $user->staffProfile->managedCohorts->contains($targetCohort->id);
        }

        return false;
    }
}
