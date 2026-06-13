<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;
use App\Services\AccessService;

class AttendancePolicy
{
    public function __construct(private AccessService $accessService) {}

    public function view(User $user, AttendanceRecord $record): bool
    {
        return $this->accessService->canAccessStudent($user, $record->student);
    }

    public function create(User $user): bool
    {
        return $user->role === 'student';
    }

    public function update(User $user, AttendanceRecord $record): bool
    {
        return in_array($user->role, ['branch_manager', 'track_admin', 'instructor'])
          && $this->accessService->canAccessStudent($user, $record->student);
    }
}
