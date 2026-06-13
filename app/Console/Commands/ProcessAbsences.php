<?php

namespace App\Console\Commands;

use App\Models\Engagement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAbsences extends Command
{
    protected $signature = 'attendance:process-absences';

    public function handle(): void
    {
        $this->logWithTime('=== Attendance Absence Processing Started ===');

        // eager-loads the underlying Course/Lab/BusinessSession records
        $engagements = Engagement::with('engageable')
            ->where('ends_at', '<=', now())
            ->whereNull('absences_processed_at')
            ->get();

        $this->logWithTime("Found {$engagements->count()} unprocessed engagements that have ended.");
        if ($engagements->isEmpty()) {
            $this->logWithTime("=== Attendance Absence Processing Completed ===\n");

            return;
        }

        $expectedStudents = Engagement::expectedStudentIdsForMany($engagements);
        $attended = Engagement::attendedStudentIdsForMany($engagements);
        $excuses = Engagement::excuseRequestsForMany($engagements);

        $deductions = [];

        foreach ($engagements as $engagement) {
            $this->logWithTime("Processing Engagement ID: {$engagement->id} ({$engagement->engageable_type})");
            $expectedSet = $expectedStudents[$engagement->id] ?? [];
            $attendedSet = $attended->get($engagement->id, []);
            $excuseSet = $excuses->get($engagement->id, collect());
            $this->logWithTime('Expected students count: '.count($expectedSet));
            foreach ($expectedSet as $studentId) {
                if (isset($attendedSet[$studentId])) {
                    $this->logWithTime("Student ID {$studentId} attended. Skipping.", 'line');

                    continue;
                }
                $isApproved = $excuseSet->get($studentId)?->status === 'approved';
                $deductions[$studentId] = ($deductions[$studentId] ?? 0) + ($isApproved ? 5 : 25);
            }
        }

        DB::transaction(function () use ($deductions, $engagements) {
            $this->applyDeductions($deductions);
            Engagement::whereIn('id', $engagements->pluck('id'))->update(['absences_processed_at' => now()]);
        });
        $this->logWithTime("=== Attendance Absence Processing Completed ===\n");
    }

    private function applyDeductions(array $deductions): void
    {
        if (empty($deductions)) {
            return;
        }

        $cases = '';
        $bindings = [];

        foreach ($deductions as $studentId => $amount) {
            $cases .= 'WHEN ? THEN attendance_balance - ? ';
            $bindings[] = $studentId;
            $bindings[] = $amount;
            $this->logWithTime("Deducting {$amount} points from Student ID {$studentId}", 'warn');
        }

        $ids = array_keys($deductions);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        DB::statement(
            "UPDATE student_profiles SET attendance_balance = CASE id {$cases} END WHERE id IN ({$placeholders})",
            [...$bindings, ...$ids]
        );
    }

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
}
