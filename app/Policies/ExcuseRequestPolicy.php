<?php

namespace App\Policies;

use App\Models\ExcuseRequest;
use App\Models\User;
use App\Services\AccessService;

class ExcuseRequestPolicy
{
    public function __construct(private AccessService $accessService) {}

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['branch_manager', 'track_admin', 'student']);
    }

    public function view(User $user, ExcuseRequest $excuseRequest): bool
    {
        if ($user->role === 'instructor') {
            return false;
        }

        return $this->accessService->canAccessStudent($user, $excuseRequest->student);
    }

    public function create(User $user): bool
    {
        return $user->role === 'student';
    }

    public function update(User $user, ExcuseRequest $excuseRequest): bool
    {
        if ($user->role !== 'student') {
            return false;
        }

        return $excuseRequest->isPending() && $this->accessService->canAccessStudent($user, $excuseRequest->student);
    }

    public function review(User $user, ExcuseRequest $excuseRequest): bool
    {
        if ($user->role !== 'track_admin') {
            return false;
        }

        return $this->accessService->canAccessStudent($user, $excuseRequest->student);
    }

    public function approve(User $user, ExcuseRequest $excuseRequest): bool
    {
        return $this->update($user, $excuseRequest);
    }

    public function reject(User $user, ExcuseRequest $excuseRequest): bool
    {
        return $this->update($user, $excuseRequest);
    }
}
