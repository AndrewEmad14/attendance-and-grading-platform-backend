<?php

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TagPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    // public function view(User $user, Tag $tag): bool
    // {
    //     return false;
    // }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->role === 'track_admin';//only admin who create tags
    }

    /**
     * Determine whether the user can update the model.
     */
    public function attachToStudent(User $user): bool //admin&instructor who can attach to student a tag
    {
        return in_array($user->role, ['track_admin', 'instructor']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function deletefromStudent(User $user, $model=null): bool
    {
        return $user->role === 'track_admin';
    }

    // /**
    //  * Determine whether the user can restore the model.
    //  */
    // public function restore(User $user, Tag $tag): bool
    // {
    //     return false;
    // }

    // /**
    //  * Determine whether the user can permanently delete the model.
    //  */
    // public function forceDelete(User $user, Tag $tag): bool
    // {
    //     return false;
    // }
}
