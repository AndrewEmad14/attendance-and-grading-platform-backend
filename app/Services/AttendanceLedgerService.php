<?php

namespace App\Services;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\ExcuseRequest;
use App\Models\Lab;
use App\Models\StudentProfile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class AttendanceLedgerService
{
    public function buildLedger(StudentProfile $student, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $student->expectedEngagementsQuery()
            ->with(['staff.user', 'engageable'])
            ->where('starts_at', '<=', now())
            ->orderBy('starts_at', 'desc');

        if (! empty($filters['date_from'])) {
            $query->whereDate('starts_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('starts_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['search'])) {
            $search = strtolower($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereHasMorph('engageable', [Course::class], fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]))
                    ->orWhereHasMorph('engageable', [Lab::class], fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]))
                    ->orWhereHasMorph('engageable', [BusinessSession::class], fn ($s) => $s->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]));
            });
        }

        $engagementsForStudent = $query->get();

        $attendanceByEngagement = $student->attendanceRecords()->get()->keyBy('engagement_id');
        $excuseByEngagement = ExcuseRequest::where('student_id', $student->id)->get()->keyBy('engagement_id');

        $allEntries = collect();

        foreach ($engagementsForStudent as $engagement) {
            $ended = $engagement->ends_at !== null && $engagement->ends_at->isPast();
            $attendance = $attendanceByEngagement->get($engagement->id);
            $present = $attendance && $attendance->arrived_at !== null;
            $excuse = $excuseByEngagement->get($engagement->id);

            if (! $ended) {
                $absenceStatus = 'present';
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

            if (! empty($filters['status']) && $filters['status'] !== $absenceStatus) {
                continue;
            }

            $allEntries->push([
                'engagement_id' => $engagement->id,
                'engagement_type' => $engagement->engagement_type_label,
                'engagement_instructor' => $engagement->staff?->user?->name,
                'name' => $engagement->engageable?->name ?? "Engagement #{$engagement->id}",
                'date' => $engagement->starts_at?->toISOString(),
                'arrived_at' => $attendance?->arrived_at?->toISOString(),
                'left_at' => $attendance?->left_at?->toISOString(),
                'absence_status' => $absenceStatus,
                'excuse_status' => $excuseStatus,
                'deduction' => $deduction,
            ]);
        }

        $page = Paginator::resolveCurrentPage();
        $items = $allEntries->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $allEntries->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()]
        );
    }
}
