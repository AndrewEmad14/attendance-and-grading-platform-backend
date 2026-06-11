<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Engagement;
use App\Models\User;

class AttendanceService
{
    public function __construct(private AccessService $accessService) {}

    public function index(User $user, int $perPage = 20)
    {
        $query = AttendanceRecord::query()->with(['student.user', 'engagement']);
        $this->accessService->scopedToUser($query, $user);

        return $query->latest()->paginate($perPage);
    }

    // POST /attendance: first scan = check-in, second = check-out, rest idempotent
    public function handleScan(User $user, int $engagementId): AttendanceRecord
    {
        $engagement = Engagement::findOrFail($engagementId);
        $now = now();
        if ($now->lt($engagement->starts_at) || $now->gt($engagement->ends_at)) {
            abort(422, 'Check in/out is only allowed during an active session window.');
        }
        $studentProfile = $user->studentProfile;

        $record = AttendanceRecord::firstOrCreate(
            ['student_id' => $studentProfile->id, 'engagement_id' => $engagementId],
            ['arrived_at' => now()]
        );

        if (! $record->wasRecentlyCreated && is_null($record->left_at)) {
            $record->update(['left_at' => now()]);
        }

        return $record->load('student.user', 'engagement');
    }

    public function correctTimestamps(AttendanceRecord $record, array $data): AttendanceRecord
    {
        $record->update(array_filter($data, fn ($v) => ! is_null($v)));

        return $record->load('student.user', 'engagement');
    }
}
