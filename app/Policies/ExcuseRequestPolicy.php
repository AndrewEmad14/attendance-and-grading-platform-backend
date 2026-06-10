<?php

namespace App\Policies;

use App\Models\ExcuseRequest;
use App\Models\User;
use App\Services\AccessService;

class ExcuseRequestPolicy
{
  public function __construct(private AccessService $accessService) {}

  /**
   * branch_manager, track_admin see scoped list.
   * Students see their own. Instructors cannot browse excuse requests.
   */
  public function viewAny(User $user): bool
  {
    return in_array($user->role, ['branch_manager', 'track_admin', 'student']);
  }

  /**
   * Can this user see this specific excuse request?
   */
  public function view(User $user, ExcuseRequest $excuseRequest): bool
  {
    if ($user->role === 'instructor') {
      return false;
    }
    $student = $excuseRequest->attendanceRecord->student;
    return $this->accessService->canAccessStudent($user, $student);
  }

  /**
   * Only students can submit excuse requests.
   */
  public function create(User $user): bool
  {
    return $user->role === 'student';
  }

  /**
   * Students can update their own pending excuse requests.
   */
  public function update(User $user, ExcuseRequest $excuseRequest): bool
  {
    if ($user->role !== 'student') {
      return false;
    }

    $studentId = $excuseRequest->attendanceRecord->student_id;
    return $excuseRequest->status === 'pending'
      && $studentId === $user->studentProfile->id;
  }

  /**
   * Only track_admin can approve/reject, and only for their own cohorts.
   */
  public function review(User $user, ExcuseRequest $excuseRequest): bool
  {
    if ($user->role !== 'track_admin') {
      return false;
    }

    $student = $excuseRequest->attendanceRecord->student;
    return $this->accessService->canAccessStudent($user, $student);
  }
}
