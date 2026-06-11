<?php

namespace App\Console\Commands;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\ExcuseRequest;
use App\Models\Lab;
use App\Models\StudentProfile;
use Illuminate\Console\Command;

class ProcessAbsences extends Command
{
    protected $signature = 'attendance:process-absences';

    protected $description = 'Deduct points for absences after session ends';

    public function handle(): void
    {
        $this->logWithTime('=== Attendance Absence Processing Started ===');

        $engagements = Engagement::where('ends_at', '<=', now())
            ->whereNull('absences_processed_at')
            ->get();

        $this->logWithTime("Found {$engagements->count()} unprocessed engagements that have ended.");

        $engagements->each(function (Engagement $engagement) {
            $this->logWithTime("Processing Engagement ID: {$engagement->id} ({$engagement->engageable_type})");

            $studentIds = $this->getExpectedStudentIds($engagement);
            $this->logWithTime('Expected students count: '.count($studentIds));

            foreach ($studentIds as $studentId) {
                $attended = $engagement->attendanceRecords()
                    ->where('student_id', $studentId)
                    ->whereNotNull('arrived_at')
                    ->exists();

                if ($attended) {
                    $this->logWithTime("Student ID {$studentId} attended. Skipping.", 'line');

                    continue;
                }

                $excuse = ExcuseRequest::where('student_id', $studentId)
                    ->where('engagement_id', $engagement->id)
                    ->first();

                $deduction = ($excuse && $excuse->status === 'approved') ? 5 : 25;
                $reason = ($excuse && $excuse->status === 'approved') ? 'Approved Excuse' : 'Unexcused Absence';

                StudentProfile::where('id', $studentId)->decrement('attendance_balance', $deduction);

                $this->logWithTime("Deducted {$deduction} points from Student ID {$studentId}. Reason: {$reason}", 'warn');
            }

            $engagement->update(['absences_processed_at' => now()]);
            $this->logWithTime("Engagement ID: {$engagement->id} marked as processed.");
        });

        $this->logWithTime("=== Attendance Absence Processing Completed ===\n");
    }

    /**
     * Format and output log messages with the current timestamp.
     */
    private function logWithTime(string $message, string $level = 'info'): void
    {
        $timestamp = '['.now()->toDateTimeString().']';
        $formattedMessage = "{$timestamp} {$message}";

        match ($level) {
            'warn' => $this->warn($formattedMessage),
            'line' => $this->line($formattedMessage),
            default => $this->info($formattedMessage),
        };
    }

    private function getExpectedStudentIds(Engagement $engagement): array
    {
        return match ($engagement->engageable_type) {
            Course::class => StudentProfile::where(
                'cohort_id',
                $engagement->engageable->cohort_id
            )->pluck('id')->toArray(),

            Lab::class => StudentProfile::where(
                'lab_group_id',
                $engagement->engageable->lab_group_id
            )->pluck('id')->toArray(),

            BusinessSession::class => StudentProfile::whereHas(
                'cohort.businessSessions',
                fn ($q) => $q->where('business_sessions.id', $engagement->engageable_id)
            )->pluck('id')->toArray(),

            default => [],
        };
    }
}
