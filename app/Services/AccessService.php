<?php

namespace App\Services;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccessService
{
    // takes a user & student and returns if user can access the student (show/update)
    public function canAccessStudent(User $user, StudentProfile $student): bool
    {
        return match ($user->role) {
            'branch_manager' => true,

            'track_admin' => $student->cohort->trackAdmins()
                ->where('staff_profiles.id', $user->staffProfile->id)
                ->exists(),

            'instructor' => $student->labGroup->labs()
                ->whereHas(
                    'engagements',
                    fn ($q) => $q->where('staff_id', $user->staffProfile->id)
                )->exists(),

            'student' => $student->id === $user->studentProfile->id,

            default => false,
        };
    }

    // appends suitable where clause depending on user role to the query
    public function scopedToUser(Builder $query, User $user): Builder
    {
        $staffId = fn () => $user->staffProfile->id;

        return match ($user->role) {
            'branch_manager' => $query,

            'track_admin' => $query->whereHas(
                'student.cohort.trackAdmins',
                fn ($q) => $q->where('staff_profiles.id', $staffId())
            ),

            'instructor' => $query->whereHas(
                'student.labGroup.labs.engagements',
                fn ($q) => $q->where('staff_id', $staffId())
            ),

            'student' => $query->whereHas(
                'student',
                fn ($q) => $q->where('id', $user->studentProfile->id)
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }
}
