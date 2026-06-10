<?php

namespace App\Policies;

use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AccessService;

class StudentProfilePolicy
{
  public function __construct(private AccessService $accessService) {}

  public function viewLedger(User $user, StudentProfile $student): bool
  {
    if ($user->isStudent()) {
      return $user->studentProfile?->id === $student->id;
    }

    return $this->accessService->canAccessStudent($user, $student);
  }
}
