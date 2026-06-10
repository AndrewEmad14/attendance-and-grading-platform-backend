<?php

namespace App\Services;

use App\Models\ExcuseRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ExcuseService
{
  public function __construct(private AccessService $accessService) {}

  // -------------------------------------------------------------------------
  // GET /excuse-requests
  // -------------------------------------------------------------------------
  public function index(User $user): Collection
  {
    $query = ExcuseRequest::query()->with([
      'attendanceRecord.student.user',
      'attendanceRecord.engagement',
      'reviewer.user',
    ]);

    // For students, scope directly. For staff roles, scope through the
    // student relation which is two hops away.
    if ($user->role === 'student') {
      $this->accessService->scopedToUser(
        $query,
        $user,
        through: 'attendanceRecord.student'
      );
    } else {
      $this->accessService->scopedToUser(
        $query,
        $user,
        through: 'attendanceRecord.student'
      );
    }

    return $query->get();
  }

  // -------------------------------------------------------------------------
  // POST /excuse-requests
  // -------------------------------------------------------------------------
  public function store(User $student, array $data, ?string $attachmentPath): ExcuseRequest
  {
    $record = AttendanceRecord::findOrFail($data['attendance_id']);

    // Students can only submit for their own records.
    if ($record->student_id !== $student->studentProfile->id) {
      abort(403, 'This action is unauthorized.');
    }

    if ($record->excuseRequest()->exists()) {
      abort(422, 'An excuse request already exists for this attendance record.');
    }

    return $record->excuseRequest()->create([
      'reason'          => $data['reason'],
      'attachment_path' => $attachmentPath,
      'status'          => 'pending',
    ]);
  }

  // -------------------------------------------------------------------------
  // GET /excuse-requests/{id}
  // -------------------------------------------------------------------------
  public function find(int $id): ExcuseRequest
  {
    return ExcuseRequest::with([
      'attendanceRecord.student.user',
      'attendanceRecord.engagement',
      'reviewer.user',
    ])->findOrFail($id);
  }

  // -------------------------------------------------------------------------
  // PATCH /excuse-requests/{id}  (student edits their pending request)
  // Policy already confirmed status is pending and user owns the record.
  // -------------------------------------------------------------------------
  public function update(ExcuseRequest $excuseRequest, array $data, ?string $attachmentPath): ExcuseRequest
  {
    $excuseRequest->update(array_filter([
      'reason'          => $data['reason'] ?? null,
      'attachment_path' => $attachmentPath,
    ], fn($v) => !is_null($v)));

    return $excuseRequest->refresh();
  }

  // -------------------------------------------------------------------------
  // POST /excuse-requests/{id}/approve
  // POST /excuse-requests/{id}/reject
  // Policy already confirmed track_admin owns this cohort.
  // -------------------------------------------------------------------------
  public function review(ExcuseRequest $excuseRequest, User $reviewer, string $status): ExcuseRequest
  {
    if ($excuseRequest->status !== 'pending') {
      abort(422, 'This excuse request has already been reviewed.');
    }

    $excuseRequest->update([
      'status'      => $status,
      'reviewed_by' => $reviewer->staffProfile->id,
      'reviewed_at' => now(),
    ]);

    return $excuseRequest->refresh();
  }
}
