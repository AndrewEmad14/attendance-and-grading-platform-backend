<?php

namespace App\Policies;

use App\Models\Track;
use App\Models\User;

class TrackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role !== 'student';
    }

    public function view(User $user, Track $track): bool
    {
        return $user->role !== 'student';
    }

    public function create(User $user): bool
    {
        return $user->role === 'branch_manager';
    }

    public function update(User $user, Track $track): bool
    {
        return $user->role === 'branch_manager';
    }

    public function delete(User $user, Track $track): bool
    {
        return $user->role === 'branch_manager';
    }
}