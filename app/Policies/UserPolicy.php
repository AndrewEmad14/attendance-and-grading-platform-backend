<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use App\Enums\Role;
class UserPolicy
{

    public function view(User $requester, User $target): bool
    {
        // branch manager can view anyone
        if ($requester->role === Role::BRANCH_MANAGER) {
            return true;
        }

        // track admin cannot view branch managers or other track admins
        if ($requester->role === Role::TRACK_ADMIN) {
            return !in_array($target->role, [
                Role::BRANCH_MANAGER,
                Role::TRACK_ADMIN,
            ]);
        }

        return false;
    }

    public function store(User $requester, string $targetRole): bool
    {
        // branch manager can create any role
        if ($requester->role === Role::BRANCH_MANAGER) {
            return true;
        }

        // track admin can only create instructors and students
        if ($requester->role === Role::TRACK_ADMIN) {
            return in_array($targetRole, [Role::INSTRUCTOR, Role::STUDENT]);
        }

        return false;
    }
    // UserPolicy.php — add update method

    public function update(User $requester, User $target): bool
    {
        // branch manager can update anyone
        if ($requester->role === Role::BRANCH_MANAGER) {
            return true;
        }

        // track admin can only update students and instructors
        if ($requester->role === Role::TRACK_ADMIN) {
            return in_array($target->role, [Role::STUDENT, Role::INSTRUCTOR]);
        }

        return false;
    }
    public function delete(User $requester, User $target): bool
    {
        // branch manager can deactivate anyone
        if ($requester->role === Role::BRANCH_MANAGER) {
            return true;
        }

        // track admin can only deactivate instructors and students
        if ($requester->role === Role::TRACK_ADMIN) {
            return in_array($target->role, [Role::INSTRUCTOR, Role::STUDENT]);
        }

        return false;
    }
}
