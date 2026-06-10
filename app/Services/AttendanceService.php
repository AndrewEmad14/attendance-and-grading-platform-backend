<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\Lab;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AttendanceService
{
  private const TYPE_MAP = [
    'lecture'          => Course::class,
    'lab'              => Lab::class,
    'business_session' => BusinessSession::class,
  ];

  public function __construct(private AccessService $accessService) {}

  public function index(User $user, int $perPage = 20): \Illuminate\Contracts\Pagination\LengthAwarePaginator
  {
    $query = AttendanceRecord::query()->with(['student.user', 'engagement']);

    $this->accessService->scopedToUser($query, $user);

    return $query->latest()->paginate($perPage);
  }

  // -------------------------------------------------------------------------
  // POST /attendance
  // Two-state scan loop: first scan = check-in, second = check-out, rest idempotent.
  // Only called with a student-authenticated user.
  // -------------------------------------------------------------------------
  public function handleScan(User $student, int $engagementId): AttendanceRecord
  {
    $engagement = Engagement::findOrFail($engagementId);

    $this->assertSessionIsActive($engagement);

    $studentProfile = $student->studentProfile;

    $record = AttendanceRecord::firstOrCreate(
      [
        'student_id'    => $studentProfile->id,
        'engagement_id' => $engagementId,
      ],
      [
        'arrived_at' => now(),
      ]
    );

    if (!$record->wasRecentlyCreated && is_null($record->left_at)) {
      // Second scan — record check-out.
      $record->update(['left_at' => now()]);
    }
    // Third+ scans: idempotent, return record unchanged.

    return $record->load('student.user', 'engagement');
  }

  // -------------------------------------------------------------------------
  // GET /attendance
  // -------------------------------------------------------------------------
  // public function index(User $user): Collection
  // {
  //   $query = AttendanceRecord::query()->with(['student.user', 'engagement']);

  //   $this->accessService->scopedToUser($query, $user);

  //   return $query->get();
  // }

  // -------------------------------------------------------------------------
  // GET /attendance/{id}
  // -------------------------------------------------------------------------
  public function find(int $id): AttendanceRecord
  {
    return AttendanceRecord::with(['student.user', 'engagement'])->findOrFail($id);
  }

  // -------------------------------------------------------------------------
  // PATCH /attendance/{id}
  // Policy already confirmed the user has access before this is called.
  // -------------------------------------------------------------------------
  public function correctTimestamps(AttendanceRecord $record, array $data): AttendanceRecord
  {
    $record->update(array_filter($data, fn($v) => !is_null($v)));

    return $record->load('student.user', 'engagement');
  }

  // -------------------------------------------------------------------------
  // Internal
  // -------------------------------------------------------------------------
  private function assertSessionIsActive(Engagement $engagement): void
  {
    $now = now();

    if ($now->lt($engagement->starts_at) || $now->gt($engagement->ends_at)) {
      abort(422, 'Check-in is only allowed during an active session window.');
    }
  }
}
