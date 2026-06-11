<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

class CoursePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool // who can list cousres
    {
        return in_array($user->role, ['track_admin', 'branch_manager', 'instructor', 'student']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Course $course): bool // who can view specific course
    {
        return in_array($user->role, ['track_admin', 'branch_manager', 'instructor', 'student']);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool // who can create courses
    {
        return $user->role === 'track_admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Course $course): bool
    {
        return $user->role === 'track_admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Course $course): bool
    {
        return $user->role === 'track_admin';
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, Course $course): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Course $course): bool
    // {
    //     return false;
    // }
}
