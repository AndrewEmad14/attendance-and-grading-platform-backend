<?php

namespace App\Policies;

use App\Models\Cohort;
use App\Models\User;

class CohortPolicy
{
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
            return $user->track_id === $cohort->track_id;
        }

        if ($user->role === 'instructor' || $user->role === 'student') {
            return $user->cohort_id === $cohort->id;
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
}
