<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BranchAnalyticsController extends Controller
{
    /**
     * GET /analytics/branch
     *
     * Branch-wide analytics visible only to branch_manager (ACC-1, R-065).
     * Returns KPI cards, per-track rollup, and weekly attendance trend.
     */
    public function __invoke(): JsonResponse
    {
        if (auth()->user()->role !== 'branch_manager') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Collect active cohorts once — every helper re-uses this list.
        $activeCohorts = DB::table('cohorts')
            ->where('is_active', true)
            ->get(['id', 'track_id', 'number']);

        if ($activeCohorts->isEmpty()) {
            return response()->json([
                'kpis' => [
                    'total_active_students' => 0,
                    'total_active_tracks' => 0,
                    'branch_attendance_pct' => null,
                    'pass_rate_pct' => null,
                ],
                'tracks' => [],
                'attendance_trend' => [],
            ]);
        }

        $activeCohortIds = $activeCohorts->pluck('id');

        return response()->json([
            'kpis' => $this->buildKpis($activeCohortIds, $activeCohorts),
            'tracks' => $this->buildTracks($activeCohorts),
            'attendance_trend' => $this->buildTrend($activeCohorts),
        ]);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    /**
     * Branch-level KPI cards.
     *
     * - total_active_students : COUNT(student_profiles) in active cohorts
     * - total_active_tracks   : COUNT(DISTINCT track_id) in active cohorts
     * - branch_attendance_pct : arrived_at IS NOT NULL / total records × 100
     * - pass_rate_pct         : students where every course score ≥ 60
     */
    private function buildKpis($activeCohortIds, $activeCohorts): array
    {
        // ── Student & track counts ────────────────────────────────────────
        $studentCount = DB::table('student_profiles')
            ->whereIn('cohort_id', $activeCohortIds)
            ->count();

        $trackCount = $activeCohorts->pluck('track_id')->unique()->count();

        // ── Attendance % ──────────────────────────────────────────────────
        // All attendance_records for engagements that belong to active cohorts.
        // An engagement "belongs" to an active cohort via:
        //   Lab      → labs.course_id → courses.cohort_id
        //   Course   → courses.cohort_id
        //   Business → business_sessions_cohorts.cohort_id
        // For attendance we count ALL records linked to active-cohort engagements.
        $attendanceStats = DB::table('attendance_records as ar')
            ->join('engagements as e', 'e.id', '=', 'ar.engagement_id')
            ->where(function ($q) use ($activeCohortIds) {
                // Lab engagements
                $q->where('e.engageable_type', 'App\\Models\\Lab')
                    ->whereExists(function ($sub) use ($activeCohortIds) {
                        $sub->from('labs')
                            ->join('courses', 'courses.id', '=', 'labs.course_id')
                            ->whereColumn('labs.id', 'e.engageable_id')
                            ->whereIn('courses.cohort_id', $activeCohortIds);
                    });
            })
            ->orWhere(function ($q) use ($activeCohortIds) {
                // Course (lecture) engagements
                $q->where('e.engageable_type', 'App\\Models\\Course')
                    ->whereExists(function ($sub) use ($activeCohortIds) {
                        $sub->from('courses')
                            ->whereColumn('courses.id', 'e.engageable_id')
                            ->whereIn('courses.cohort_id', $activeCohortIds);
                    });
            })
            ->orWhere(function ($q) use ($activeCohortIds) {
                // Business-session engagements
                $q->where('e.engageable_type', 'App\\Models\\BusinessSession')
                    ->whereExists(function ($sub) use ($activeCohortIds) {
                        $sub->from('business_sessions_cohorts as bsc')
                            ->whereColumn('bsc.business_session_id', 'e.engageable_id')
                            ->whereIn('bsc.cohort_id', $activeCohortIds);
                    });
            })
            ->selectRaw('COUNT(*) as total, COUNT(ar.arrived_at) as attended')
            ->first();

        $attendancePct = ($attendanceStats->total > 0)
            ? round(($attendanceStats->attended / $attendanceStats->total) * 100, 1)
            : null;

        // ── Pass rate ─────────────────────────────────────────────────────
        $passRatePct = $this->calcPassRate($activeCohortIds);

        return [
            'total_active_students' => $studentCount,
            'total_active_tracks' => $trackCount,
            'branch_attendance_pct' => $attendancePct,
            'pass_rate_pct' => $passRatePct,
        ];
    }

    /**
     * Per-track rollup — one entry per active cohort.
     *
     * - attendance_pct        : attended / total for that cohort's engagements
     * - total_delivered_hours : SUM(scheduled_hours) for Lab+Course engagements in this cohort
     * - pass_rate_pct         : per-cohort pass rate
     * - at_risk_count         : from v_at_risk_students
     */
    private function buildTracks($activeCohorts): array
    {
        $rows = [];

        foreach ($activeCohorts as $cohort) {
            $cohortIds = collect([$cohort->id]);
            $trackId = $cohort->track_id;

            // Track name
            $trackName = DB::table('tracks')->where('id', $trackId)->value('name');

            // Student count
            $studentCount = DB::table('student_profiles')
                ->where('cohort_id', $cohort->id)
                ->count();

            // Attendance % for this cohort
            $attStats = $this->cohortAttendanceStats($cohort->id);
            $attendancePct = ($attStats->total > 0)
                ? round(($attStats->attended / $attStats->total) * 100, 1)
                : null;

            // Total delivered (scheduled) hours: Lab + Course engagements only
            $deliveredHours = DB::table('engagements as e')
                ->where(function ($q) use ($cohort) {
                    $q->where('e.engageable_type', 'App\\Models\\Lab')
                        ->whereExists(function ($sub) use ($cohort) {
                            $sub->from('labs')
                                ->join('courses', 'courses.id', '=', 'labs.course_id')
                                ->whereColumn('labs.id', 'e.engageable_id')
                                ->where('courses.cohort_id', $cohort->id);
                        });
                })
                ->orWhere(function ($q) use ($cohort) {
                    $q->where('e.engageable_type', 'App\\Models\\Course')
                        ->whereExists(function ($sub) use ($cohort) {
                            $sub->from('courses')
                                ->whereColumn('courses.id', 'e.engageable_id')
                                ->where('courses.cohort_id', $cohort->id);
                        });
                })
                ->sum('e.scheduled_hours');

            // Pass rate for this cohort
            $passRate = $this->calcPassRate($cohortIds);

            // At-risk count via the DB view
            $atRiskCount = DB::table('v_at_risk_students')
                ->where('cohort_id', $cohort->id)
                ->count();

            $rows[] = [
                'track_id' => $trackId,
                'track_name' => $trackName,
                'cohort_id' => $cohort->id,
                'cohort_number' => $cohort->number,
                'student_count' => $studentCount,
                'attendance_pct' => $attendancePct,
                'total_delivered_hours' => (float) $deliveredHours,
                'pass_rate_pct' => $passRate,
                'at_risk_count' => $atRiskCount,
            ];
        }

        return $rows;
    }

    /**
     * Weekly attendance trend.
     *
     * - One entry per calendar week (Saturday as week_start) where any active-cohort
     *   engagement existed.
     * - by_track always lists ALL active tracks; null if no engagement that week.
     */
    private function buildTrend($activeCohorts): array
    {
        $activeCohortIds = $activeCohorts->pluck('id');
        $trackIds = $activeCohorts->pluck('track_id')->unique()->values();

        // Inner query: resolve the cohort_id each engagement belongs to,
        // and compute its Saturday-anchored week_start from starts_at.
        // Wrapping as a subquery lets Postgres GROUP BY the alias cleanly.
        $inner = DB::table('engagements as e')
            ->join('attendance_records as ar', 'ar.engagement_id', '=', 'e.id')
            ->leftJoin('courses as c_direct', function ($join) {
                $join->on('c_direct.id', '=', 'e.engageable_id')
                    ->where('e.engageable_type', '=', 'App\\Models\\Course');
            })
            ->leftJoin('labs as l', function ($join) {
                $join->on('l.id', '=', 'e.engageable_id')
                    ->where('e.engageable_type', '=', 'App\\Models\\Lab');
            })
            ->leftJoin('courses as c_via_lab', 'c_via_lab.id', '=', 'l.course_id')
            ->leftJoin('business_sessions_cohorts as bsc', function ($join) {
                $join->on('bsc.business_session_id', '=', 'e.engageable_id')
                    ->where('e.engageable_type', '=', 'App\\Models\\BusinessSession');
            })
            ->where(function ($q) use ($activeCohortIds) {
                $q->whereIn('c_direct.cohort_id', $activeCohortIds)
                    ->orWhereIn('c_via_lab.cohort_id', $activeCohortIds)
                    ->orWhereIn('bsc.cohort_id', $activeCohortIds);
            })
            ->selectRaw("
                COALESCE(c_direct.cohort_id, c_via_lab.cohort_id, bsc.cohort_id) AS resolved_cohort_id,
                ar.arrived_at                                                     AS arrived_at,
                (DATE_TRUNC('week', e.starts_at::date + INTERVAL '1 day') - INTERVAL '1 day')::date AS week_start
            ");

        $engagementRows = DB::table(DB::raw("({$inner->toSql()}) as inner_rows"))
            ->mergeBindings($inner)
            ->selectRaw('resolved_cohort_id, week_start, COUNT(*) as total, COUNT(arrived_at) as attended')
            ->groupBy('resolved_cohort_id', 'week_start')
            ->orderBy('week_start')
            ->get()
            ->map(function ($row) {
                // alias back to 'cohort_id' for the rest of the method
                $row->cohort_id = $row->resolved_cohort_id;

                return $row;
            });

        if ($engagementRows->isEmpty()) {
            return [];
        }

        // Build a map: track_id per cohort_id
        $cohortToTrack = $activeCohorts->pluck('track_id', 'id');

        // Collect all distinct weeks
        $weeks = $engagementRows->pluck('week_start')->unique()->sort()->values();

        // Build: week → track_id → [total, attended]
        $weekTrackMap = [];
        foreach ($engagementRows as $row) {
            $trackId = $cohortToTrack->get($row->cohort_id);
            if (! $trackId) {
                continue;
            }
            $week = $row->week_start;
            if (! isset($weekTrackMap[$week][$trackId])) {
                $weekTrackMap[$week][$trackId] = ['total' => 0, 'attended' => 0];
            }
            $weekTrackMap[$week][$trackId]['total'] += $row->total;
            $weekTrackMap[$week][$trackId]['attended'] += $row->attended;
        }

        $trend = [];
        foreach ($weeks as $week) {
            $trackData = $weekTrackMap[$week] ?? [];

            // Branch-level pct: sum across all tracks this week
            $branchTotal = array_sum(array_column($trackData, 'total'));
            $branchAttended = array_sum(array_column($trackData, 'attended'));
            $branchPct = ($branchTotal > 0)
                ? round(($branchAttended / $branchTotal) * 100, 1)
                : null;

            $byTrack = [];
            foreach ($trackIds as $trackId) {
                if (isset($trackData[$trackId]) && $trackData[$trackId]['total'] > 0) {
                    $pct = round(
                        ($trackData[$trackId]['attended'] / $trackData[$trackId]['total']) * 100,
                        1
                    );
                } else {
                    $pct = null; // track had no engagement sessions this week
                }
                $byTrack[] = ['track_id' => $trackId, 'pct' => $pct];
            }

            $trend[] = [
                'week_start' => $week,
                'branch_pct' => $branchPct,
                'by_track' => $byTrack,
            ];
        }

        return $trend;
    }

    // ── Shared utilities ──────────────────────────────────────────────────

    /**
     * Compute attendance stats (total, attended) for a single cohort.
     * Counts every attendance_record whose engagement traces back to this cohort.
     */
    private function cohortAttendanceStats(int $cohortId): object
    {
        return DB::table('attendance_records as ar')
            ->join('engagements as e', 'e.id', '=', 'ar.engagement_id')
            ->where(function ($q) use ($cohortId) {
                $q->where('e.engageable_type', 'App\\Models\\Lab')
                    ->whereExists(function ($sub) use ($cohortId) {
                        $sub->from('labs')
                            ->join('courses', 'courses.id', '=', 'labs.course_id')
                            ->whereColumn('labs.id', 'e.engageable_id')
                            ->where('courses.cohort_id', $cohortId);
                    });
            })
            ->orWhere(function ($q) use ($cohortId) {
                $q->where('e.engageable_type', 'App\\Models\\Course')
                    ->whereExists(function ($sub) use ($cohortId) {
                        $sub->from('courses')
                            ->whereColumn('courses.id', 'e.engageable_id')
                            ->where('courses.cohort_id', $cohortId);
                    });
            })
            ->orWhere(function ($q) use ($cohortId) {
                $q->where('e.engageable_type', 'App\\Models\\BusinessSession')
                    ->whereExists(function ($sub) use ($cohortId) {
                        $sub->from('business_sessions_cohorts as bsc')
                            ->whereColumn('bsc.business_session_id', 'e.engageable_id')
                            ->where('bsc.cohort_id', $cohortId);
                    });
            })
            ->selectRaw('COUNT(*) as total, COUNT(ar.arrived_at) as attended')
            ->first();
    }

    /**
     * Pass rate for a set of cohorts.
     *
     * A student "passes" when ALL their course scores ≥ 60.
     * Course score uses the canonical grading formula:
     *
     *   effective = COALESCE(override_score, raw_score)
     *   days_late = GREATEST(0, EXTRACT(DAY FROM (sub.created_at - cd.due_date)))
     *   penalty   = days_late * 0.25 * cd.max_score   (labs only)
     *   component = GREATEST(0, effective - penalty) / max_score * course_weight
     *   course_score = SUM(component) per student × course
     *
     * Returns null when there are no students (avoids division-by-zero).
     *
     * Strategy:
     *  1. Compute every student × course score in SQL (no data loaded into PHP).
     *  2. Flag failing courses (score < 60) per student.
     *  3. Pass = student has zero failing courses.
     */
    private function calcPassRate($cohortIds): ?float
    {
        // All students in these cohorts
        $studentRows = DB::table('student_profiles')
            ->whereIn('cohort_id', $cohortIds)
            ->get(['id', 'user_id', 'cohort_id']);

        if ($studentRows->isEmpty()) {
            return null;
        }

        $totalStudents = $studentRows->count();
        $studentProfileIds = $studentRows->pluck('id');

        // All courses across these cohorts
        $courseIds = DB::table('courses')
            ->whereIn('cohort_id', $cohortIds)
            ->pluck('id');

        if ($courseIds->isEmpty()) {
            // No courses yet — everyone passes (no failing evidence)
            return 100.0;
        }

        // Compute per-student × per-course score in pure SQL.
        // Uses GREATEST(0, ...) to floor at zero, and Postgres EXTRACT for days.
        $scoreRows = DB::table('submissions as sub')
            ->join('courses_deliverables as cd', 'cd.id', '=', 'sub.deliverable_id')
            ->whereIn('sub.student_id', $studentProfileIds)
            ->whereIn('cd.course_id', $courseIds)
            ->selectRaw('
                sub.student_id,
                cd.course_id,
                SUM(
                    CASE
                        WHEN cd.type = \'lab\' AND cd.due_date IS NOT NULL
                            THEN GREATEST(0,
                                COALESCE(sub.override_score, sub.raw_score)
                                - GREATEST(0, EXTRACT(DAY FROM (sub.created_at - cd.due_date))) * 0.25 * cd.max_score
                            ) / NULLIF(cd.max_score, 0) * cd.course_weight
                        ELSE
                            COALESCE(sub.override_score, sub.raw_score)
                            / NULLIF(cd.max_score, 0) * cd.course_weight
                    END
                ) AS course_score
            ')
            ->groupBy('sub.student_id', 'cd.course_id')
            ->get();

        // Index: student_id → [course_id → score]
        $scoreMap = [];
        foreach ($scoreRows as $row) {
            $scoreMap[$row->student_id][$row->course_id] = (float) $row->course_score;
        }

        // A student fails if ANY course score < 60.
        // Students with NO submissions at all have a score of 0 for every course
        // (treat as failing unless there are no courses — handled above).
        $passingCount = 0;
        foreach ($studentRows as $student) {
            $failing = false;
            foreach ($courseIds as $courseId) {
                $score = $scoreMap[$student->id][$courseId] ?? 0.0;
                if ($score < 60) {
                    $failing = true;
                    break;
                }
            }
            if (! $failing) {
                $passingCount++;
            }
        }

        return round(($passingCount / $totalStudents) * 100, 1);
    }
}
