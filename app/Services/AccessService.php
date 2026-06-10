<?php

namespace App\Services;

use App\Models\User;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Builder;

class AccessService
{
  /**
   * Service to check if a user can access a specific student
   * Used in policies for single-record (show, update) authorization.
   */
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
          fn($q) =>
          $q->where('staff_id', $user->staffProfile->id)
        )->exists(),

      'student' => $student->id === $user->studentProfile->id,

      default => false,
    };
  }

  /**
   * given a query builder, add WHERE clauses so it only returns records the user is allowed to see.
   * Used in services for collection endpoints.
   *
   * The $through parameter is the dot-notation path from the query's root
   * model to the StudentProfile relation.
   */
  public function scopedToUser(Builder $query, User $user, string $through = 'student'): Builder
  {
    $staffId = fn() => $user->staffProfile->id;

    return match ($user->role) {
      'branch_manager' => $query,

      'track_admin' => $query->whereHas(
        "{$through}.cohort.trackAdmins",
        fn($q) => $q->where('staff_profiles.id', $staffId())
      ),

      'instructor' => $query->whereHas(
        "{$through}.labGroup.labs.engagements",
        fn($q) => $q->where('staff_id', $staffId())
      ),

      'student' => $query->whereHas(
        $through,
        fn($q) => $q->where('id', $user->studentProfile->id)
      ),

      default => $query->whereRaw('1 = 0'),
    };
  }
}
