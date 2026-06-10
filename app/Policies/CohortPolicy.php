<?php

namespace App\Policies;

use App\Models\Cohort;
use App\Models\User;
use App\Services\AccessService;

class CohortPolicy
{
    public function __construct(private AccessService $accessService) {}
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Cohort $cohort): bool
    {
        if ($user->role === 'branch_manager') {
            return true;
        }

        if ($user->role === 'track_admin') {
            return $user->staffProfile && $user->staffProfile->managedCohorts->contains($cohort->id);
        }

        if ($user->role === 'instructor') {
            return $cohort->engagements()->where('staff_id', $user->staffProfile?->id)->exists();
        }

        if ($user->role === 'student') {
            return $user->studentProfile && $user->studentProfile->cohort_id === $cohort->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->role === 'branch_manager';
    }

    public function update(User $user, Cohort $cohort): bool
    {
        return $user->role === 'branch_manager';
    }

    public function delete(User $user, Cohort $cohort): bool
    {
        return $user->role === 'branch_manager';
    }

    public function viewAtRisk(User $user, Cohort $cohort): bool
    {
        if ($user->isBranchManager()) {
            return true;
        }

        if ($user->isTrackAdmin()) {
            return $cohort->admins()
                ->where('staff_id', $user->staffProfile->id)
                ->exists();
        }

        return false;
    }
}
