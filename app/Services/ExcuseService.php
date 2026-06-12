<?php

namespace App\Services;

use App\Models\ExcuseRequest;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class ExcuseService
{
    public function __construct(private AccessService $accessService) {}

    public function index(User $user, int $perPage = 20): LengthAwarePaginator
    {
        $query = ExcuseRequest::query()->with([
            'student.user',
            'engagement',
            'reviewer.user',
        ]);
        $this->accessService->scopedToUser($query, $user);

        return $query->latest()->paginate($perPage);
    }

    public function store(User $user, array $data, ?string $attachment): ExcuseRequest
    {
        $studentId = $user->studentProfile->id;
        $engagementId = $data['engagement_id'];
        if (ExcuseRequest::where('student_id', $studentId)->where('engagement_id', $engagementId)->exists()) {
            abort(422, 'An excuse request already exists for this engagement.');
        }

        $attachmentPath = null;
        if (isset($data['attachment'])) {
            // Store the file in the 'storage/app/public/excuses' directory
            $attachmentPath = $data['attachment']->store('excuses', 'public');
        }

        return ExcuseRequest::create([
            'student_id' => $studentId,
            'engagement_id' => $engagementId,
            'reason' => $data['reason'],
            'attachment_path' => $attachmentPath,
            'status' => ExcuseRequest::STATUS_PENDING,
        ]);
    }

    public function update(ExcuseRequest $excuseRequest, array $data, ?string $attachmentPath): ExcuseRequest
    {
        $excuseRequest->update(array_filter([
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $attachmentPath,
        ], fn ($v) => ! is_null($v)));

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
        if (
            $status === 'approved' &&
            $excuseRequest->engagement->absences_processed_at !== null
        ) {
            $excuseRequest->student->increment('attendance_balance', 20);
        }

        return $excuseRequest->refresh();
    }
}
