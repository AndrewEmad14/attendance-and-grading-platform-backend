<?php

namespace App\Policies;

use App\Enums\CompensationType;
use App\Enums\Role;
use App\Models\Announcement;
use App\Models\Cohort;
use App\Models\User;

class AnnouncementPolicy
{
    /**
     * Can this user view announcements for this cohort?
     */
    public function viewAny(User $user, Cohort $cohort): bool
    {
        if ($user->role === Role::BRANCH_MANAGER) {
            return true;
        }

        if ($user->role === Role::STUDENT) {
            return $user->studentProfile?->cohort_id === $cohort->id;
        }

        // From here on, user is staff (Track Admin or Instructor) — staffProfile exists

        if ($cohort->trackAdmins()->where('staff_id', $user->staffProfile->id)->exists()) {
            return true;
        }

        $engagements = $cohort->engagements()
            ->where('staff_id', $user->staffProfile->id);

        if ($user->staffProfile->compensation_type === CompensationType::EXTERNAL) {
            $engagements->where('starts_at', '<=', now())
                ->where('ends_at', '>=', now());
        }

        return $engagements->exists();
    }

    /**
     * Can this user post an announcement to this cohort?
     */
    public function store(User $user, Cohort $cohort): bool
    {
        if ($user->role === Role::BRANCH_MANAGER) {
            return true;
        }

        if ($cohort->trackAdmins()->where('staff_id', $user->staffProfile->id)->exists()) {
            return true;
        }

        // ANN-2: active engagement window required for instructors (internal or external)
        return $cohort->engagements()
            ->where('staff_id', $user->staffProfile->id)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->exists();
    }

    /**
     * Can this user update this announcement?
     */
    public function update(User $user, Announcement $announcement): bool
    {
        if ($user->role === Role::BRANCH_MANAGER) {
            return true;
        }
        if ($announcement->cohort_id === null) {
            return false; // broadcasts: BM only
        }

        if ($announcement->cohort->trackAdmins()->where('staff_id', $user->staffProfile->id)->exists()) {
            return true;
        }

        if ($announcement->staff_id === $user->staffProfile->id) {
            return $this->instructorHasWindowFor($user, $announcement);
        }
        
    
        return false;
    }

    /**
     * Can this user delete this announcement?
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        if ($user->role === Role::BRANCH_MANAGER) {
            return true;
        }

        if ($announcement->cohort->trackAdmins()->where('staff_id', $user->staffProfile->id)->exists()) {
            return true;
        }

        if ($announcement->staff_id === $user->staffProfile->id) {
            return $this->instructorHasWindowFor($user, $announcement);
        }

        return false;
    }

    /**
     * Verifies the instructor's engagement was active when the post was
     * created, and is still active now.
     */
    protected function instructorHasWindowFor(User $user, Announcement $announcement): bool
    {
        $query = $announcement->cohort->engagements()
            ->where('staff_id', $user->staffProfile->id)
            ->where('starts_at', '<=', $announcement->created_at)
            ->where('ends_at', '>=', now());

        

        return $query->exists();
    }
}
