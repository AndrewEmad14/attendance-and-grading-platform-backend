<?php

namespace App\Services;

use App\Models\ExcuseRequest;
use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ExcuseService
{
  public function __construct(private AccessService $accessService) {}

  public function index(User $user, int $perPage = 20): LengthAwarePaginator
  {
    $query = ExcuseRequest::query()->with([
      'attendanceRecord.student.user',
      'attendanceRecord.engagement',
      'reviewer.user',
    ]);
    $this->accessService->scopedToUser($query, $user);
    return $query->latest()->paginate($perPage);
  }

  public function store(User $student, array $data, ?string $attachment): ExcuseRequest
  {
    $record = AttendanceRecord::findOrFail($data['attendance_id']);
    if ($record->student_id !== $student->studentProfile->id) {
      abort(403, 'This action is unauthorized.');
    } else if ($record->excuseRequest()->exists()) {
      abort(422, 'An excuse request already exists for this attendance record.');
    }
    return $record->excuseRequest()->create([
      'reason' => $data['reason'],
      'attachment_path' => $attachment,
      'status' => 'pending',
    ]);
  }

  public function update(ExcuseRequest $excuseRequest, array $data, ?string $attachmentPath): ExcuseRequest
  {
    $excuseRequest->update(array_filter([
      'reason' => $data['reason'] ?? null,
      'attachment_path' => $attachmentPath,
    ], fn($v) => !is_null($v)));
    return $excuseRequest->refresh();
  }

  public function review(ExcuseRequest $excuseRequest, User $reviewer, string $status): ExcuseRequest
  {
    if ($excuseRequest->status !== 'pending') {
      abort(422, 'This excuse request has already been reviewed.');
    }
    $excuseRequest->update([
      'status' => $status,
      'reviewed_by' => $reviewer->staffProfile->id,
      'reviewed_at' => now(),
    ]);
    return $excuseRequest->refresh();
  }
}
