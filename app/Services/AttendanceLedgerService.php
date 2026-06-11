<?php

namespace App\Services;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\ExcuseRequest;
use App\Models\Lab;
use App\Models\StudentProfile;
use Illuminate\Support\Collection;

class AttendanceLedgerService
{
    private const UNEXCUSED_DEDUCTION = 25;

    private const EXCUSED_DEDUCTION = 5;

    // GET /students/{student}/attendance-ledger
    // Returns all engagements student is expected to attend, attendance for each engagement,
    // absence status, excuses, deduction per entry, running + current attendance balance
    public function buildLedger(StudentProfile $student): array
    {
        // Load all three sets of engagements this student should attend.
        $engagements = $this->fetchEngagementsForStudent($student);

        // Index attendance records by engagement_id for O(1) lookup
        $attendanceByEngagement = $student
            ->attendanceRecords()
            ->get()
            ->keyBy('engagement_id');

        // Index excuse requests by engagement_id for O(1) lookup
        $excuseByEngagement = ExcuseRequest::where('student_id', $student->id)
            ->get()
            ->keyBy('engagement_id');

        $entries = [];

        foreach ($engagements as $engagement) {
            $ended = $engagement->ends_at !== null && $engagement->ends_at->isPast();
            $attendance = $attendanceByEngagement->get($engagement->id);
            $present = $attendance && $attendance->arrived_at !== null;
            $excuse = $excuseByEngagement->get($engagement->id);

            if (! $ended) {
                // Session hasn't ended yet — no deduction, no verdict
                $entries[] = [
                    'engagement_id' => $engagement->id,
                    'engagement_type' => $this->engagementType($engagement),
                    'name' => $this->engagementName($engagement),
                    'date' => $engagement->starts_at?->toISOString(),
                    'arrived_at' => $attendance?->arrived_at?->toISOString(),
                    'left_at' => $attendance?->left_at?->toISOString(),
                    'absence_status' => $present ? 'present' : 'upcoming',
                    'excuse_status' => null,
                    'deduction' => 0,
                ];

                continue;
            }

            if ($present) {
                $entries[] = [
                    'engagement_id' => $engagement->id,
                    'engagement_type' => $this->engagementType($engagement),
                    'name' => $this->engagementName($engagement),
                    'date' => $engagement->starts_at?->toISOString(),
                    'arrived_at' => $attendance->arrived_at->toISOString(),
                    'left_at' => $attendance->left_at?->toISOString(),
                    'absence_status' => 'present',
                    'excuse_status' => null,
                    'deduction' => 0,
                ];

                continue;
            }

            // Ended + absent — show the deduction that the command applied
            $deduction = ($excuse && $excuse->status === 'approved')
                ? self::EXCUSED_DEDUCTION
                : self::UNEXCUSED_DEDUCTION;

            $entries[] = [
                'engagement_id' => $engagement->id,
                'engagement_type' => $this->engagementType($engagement),
                'name' => $this->engagementName($engagement),
                'date' => $engagement->starts_at?->toISOString(),
                'arrived_at' => null,
                'left_at' => null,
                'absence_status' => 'absent',
                'excuse_status' => $excuse?->status ?? 'none',
                'deduction' => -$deduction,
            ];
        }

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->user->name,
            ],
            // current_balance is the single source of truth — set by the command,
            // never recalculated here independently
            'current_balance' => $student->attendance_balance,
            'entries' => $entries,
        ];
    }

    // All engagements a student should attend accross types
    private function fetchEngagementsForStudent(StudentProfile $student): Collection
    {
        // lecture engagements belonging to the student's cohort's courses
        $lectureEngagements = Engagement::with('engageable')
            ->whereHasMorph(
                'engageable',
                [Course::class],
                fn ($q) => $q->where('cohort_id', $student->cohort_id)
            )
            ->get();

        // labs that belong to the student's lab group
        $labEngagements = Engagement::with('engageable')
            ->whereHasMorph(
                'engageable',
                [Lab::class],
                fn ($q) => $q->where('lab_group_id', $student->lab_group_id)
            )
            ->get();

        // business sessions linked to the student's cohort
        $businessEngagements = Engagement::with('engageable')
            ->whereHasMorph(
                'engageable',
                [BusinessSession::class],
                fn ($q) => $q->whereHas(
                    'cohorts',
                    fn ($c) => $c->where('cohorts.id', $student->cohort_id)
                )
            )
            ->get();

        return $lectureEngagements
            ->concat($labEngagements)
            ->concat($businessEngagements)
            ->sortBy('starts_at')
            ->values();
    }

    private function engagementType(Engagement $engagement): string
    {
        return match ($engagement->engageable_type) {
            Course::class => 'lecture',
            Lab::class => 'lab',
            BusinessSession::class => 'business_session'
        };
    }

    private function engagementName(Engagement $engagement): string
    {
        return $engagement->engageable?->name ?? "Engagement #{$engagement->id}";
    }
}
