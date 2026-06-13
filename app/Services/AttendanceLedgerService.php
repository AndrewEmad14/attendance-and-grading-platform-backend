<?php

namespace App\Services;

use App\Models\ExcuseRequest;
use App\Models\StudentProfile;

class AttendanceLedgerService
{
    // Returns all engagements student is expected to attend, attendance for each engagement,
    // absence status, excuses, deduction per entry, running + current attendance balance
    public function buildLedger(StudentProfile $student): array
    {
        $engagementsForStudent = $student->expectedEngagementsQuery()
            ->orderBy('starts_at')
            ->get();

        // Index attendances & excuses by engagement_id for O(1) lookup
        $attendanceByEngagement = $student->attendanceRecords()->get()->keyBy('engagement_id');
        $excuseByEngagement = ExcuseRequest::where('student_id', $student->id)->get()->keyBy('engagement_id');
        $entries = [];

        foreach ($engagementsForStudent as $engagement) {
            $ended = $engagement->ends_at !== null && $engagement->ends_at->isPast();
            $attendance = $attendanceByEngagement->get($engagement->id);
            $present = $attendance && $attendance->arrived_at !== null;
            $excuse = $excuseByEngagement->get($engagement->id);

            if (! $ended) {
                $absenceStatus = $present ? 'present' : 'upcoming';
                $excuseStatus = null;
                $deduction = 0;
            } elseif ($present) {
                $absenceStatus = 'present';
                $excuseStatus = null;
                $deduction = 0;
            } else {
                $absenceStatus = 'absent';
                $excuseStatus = $excuse?->status ?? 'none';
                $deduction = ($excuse && $excuse->status === 'approved') ? -5 : -25;
            }

            $entries[] = [
                'engagement_id' => $engagement->id,
                'engagement_type' => $engagement->type,
                'name' => $engagement->engageable?->name ?? "Engagement #{$engagement->id}",
                'date' => $engagement->starts_at?->toISOString(),
                'arrived_at' => $attendance?->arrived_at?->toISOString(),
                'left_at' => $attendance?->left_at?->toISOString(),
                'absence_status' => $absenceStatus,
                'excuse_status' => $excuseStatus,
                'deduction' => $deduction,
            ];
        }

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->user->name,
            ],
            'current_balance' => $student->attendance_balance,
            'entries' => $entries,
        ];
    }
}
