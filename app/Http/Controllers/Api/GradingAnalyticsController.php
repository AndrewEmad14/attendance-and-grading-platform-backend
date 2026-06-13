<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\LabGroup;
use App\Models\StudentProfile;
use App\Models\Views\AtRiskStudent;
use Illuminate\Support\Facades\DB;

class GradingAnalyticsController extends Controller
{
    // returns grade distribution + grader consistency for a cohort
    public function cohortGrades(int $cohortId)
    {
        if (! in_array(auth()->user()->role, ['track_admin', 'branch_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // get all courses for this cohort with deliverables and graded submissions
        $courses = Course::where('cohort_id', $cohortId)
            ->with(['deliverables.submissions'])
            ->get();

        // 1. Calculate the total score for each student in each course
        $courseGrades = [];

        foreach ($courses as $course) {
            foreach ($course->deliverables as $deliverable) {
                foreach ($deliverable->submissions as $submission) {

                    $finalScore = $submission->override_score ?? $submission->raw_score;

                    if ($finalScore === null || $deliverable->max_score == 0) {
                        continue;
                    }

                    $pointsEarned = ($finalScore / $deliverable->max_score) * $deliverable->course_weight;

                    $studentId = $submission->student_id;
                    $courseId = $course->id;

                    if (! isset($courseGrades[$studentId][$courseId])) {
                        $courseGrades[$studentId][$courseId] = 0;
                    }
                    $courseGrades[$studentId][$courseId] += $pointsEarned;
                }
            }
        }

        $buckets = [
            '0_59' => 0,
            '60_69' => 0,
            '70_79' => 0,
            '80_89' => 0,
            '90_100' => 0,
        ];

        foreach ($courseGrades as $studentId => $studentCourses) {
            foreach ($studentCourses as $courseId => $totalScore) {

                $totalScore = round($totalScore, 2);

                if ($totalScore < 60) {
                    $buckets['0_59']++;
                } elseif ($totalScore < 70) {
                    $buckets['60_69']++;
                } elseif ($totalScore < 80) {
                    $buckets['70_79']++;
                } elseif ($totalScore < 90) {
                    $buckets['80_89']++;
                } else {
                    $buckets['90_100']++;
                }
            }
        }

        // grader consistency: mean score per lab group
        $labGroups = LabGroup::where('cohort_id', $cohortId)->get();
        $consistency = [];
        foreach ($labGroups as $group) {
            $groupStudentIds = StudentProfile::where('lab_group_id', $group->id)
                ->pluck('id');

            $scores = [];
            foreach ($courseGrades as $studentId => $courseScores) {
                if ($groupStudentIds->contains($studentId)) {
                    $scores[] = array_sum($courseScores);
                }
            }

            $consistency[] = [
                'lab_group_id' => $group->id,
                'name' => $group->name,
                'mean_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : 0,
            ];
        }

        // count students with at least one tag
        $taggedCount = DB::table('students_tags')
            ->whereIn('student_id', StudentProfile::where('cohort_id', $cohortId)->pluck('id'))
            ->distinct('student_id')
            ->count('student_id');

        return response()->json([
            'cohort_id' => $cohortId,
            'grade_distribution' => $buckets,
            'grader_consistency' => $consistency,
            'tagged_student_count' => $taggedCount,
        ]);
    }

    // returns grade distribution for a single lab group
    public function labGroupGrades(int $labGroupId)
    {
        if (! in_array(auth()->user()->role, ['track_admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // get all students in this lab group
        $studentIds = StudentProfile::where('lab_group_id', $labGroupId)
            ->pluck('id');

        // get their submissions with deliverables
        $submissions = DB::table('submissions')
            ->join('courses_deliverables', 'submissions.deliverable_id', '=', 'courses_deliverables.id')
            ->whereIn('submissions.student_id', $studentIds)
            ->select(
                'submissions.student_id',
                'submissions.raw_score',
                'submissions.override_score',
                'courses_deliverables.max_score',
                'courses_deliverables.course_weight',
                'courses_deliverables.course_id'
            )
            ->get();

        // compute normalized score per student per course
        $studentScores = [];
        foreach ($submissions as $sub) {
            $effectiveRaw = $sub->override_score ?? $sub->raw_score;
            if ($effectiveRaw === null || $sub->max_score == 0) {
                continue;
            }
            $normalized = round(($effectiveRaw / $sub->max_score) * $sub->course_weight, 2);
            $studentScores[$sub->student_id][$sub->course_id] =
                ($studentScores[$sub->student_id][$sub->course_id] ?? 0) + $normalized;
        }

        // build distribution buckets
        $buckets = ['0_59' => 0, '60_69' => 0, '70_79' => 0, '80_89' => 0, '90_100' => 0];
        foreach ($studentScores as $courses) {
            foreach ($courses as $score) {
                if ($score < 60) {
                    $buckets['0_59']++;
                } elseif ($score < 70) {
                    $buckets['60_69']++;
                } elseif ($score < 80) {
                    $buckets['70_79']++;
                } elseif ($score < 90) {
                    $buckets['80_89']++;
                } else {
                    $buckets['90_100']++;
                }
            }
        }

        return response()->json([
            'lab_group_id' => $labGroupId,
            'grade_distribution' => $buckets,
        ]);
    }

    // returns list of at-risk students in a cohort (ANL-1)
    public function atRiskStudents(int $cohortId)
    {
        $user = auth()->user();

        // 1. Authorization checks
        if (! in_array($user->role, ['track_admin', 'branch_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 2. Existence check
        $cohortExists = DB::table('cohorts')->where('id', $cohortId)->exists();
        if (! $cohortExists) {
            return response()->json(['message' => 'Cohort not found'], 404);
        }

        // 3. Fetch at-risk students for the cohort
        $atRisk = AtRiskStudent::where('cohort_id', $cohortId)
            ->with(['student.studentProfile'])
            ->get();

        // 4. Fetch all course grades for this cohort to identify failing courses
        $courseScores = DB::table('courses as c')
            ->join('student_profiles as sp', 'sp.cohort_id', '=', 'c.cohort_id')
            ->leftJoin('v_student_scores as vs', function ($join) {
                $join->on('vs.student_id', '=', 'sp.user_id')
                    ->on('vs.course_id', '=', 'c.id');
            })
            ->where('sp.cohort_id', $cohortId)
            ->select('sp.user_id as student_id', 'c.id as course_id', 'c.name as course_name', DB::raw('COALESCE(SUM(vs.component_score), 0) as score'))
            ->groupBy('sp.user_id', 'c.id', 'c.name')
            ->get()
            ->groupBy('student_id');

        // 5. Format response
        $result = $atRisk->map(function ($record) use ($courseScores) {
            $studentUserId = $record->student_id;

            // Get all course scores for this student
            $studentCourses = $courseScores->get($studentUserId, collect());

            // Filter out only the failing courses (score < 60)
            $failingCourses = $studentCourses->filter(function ($course) {
                return $course->score < 60;
            })->map(function ($course) {
                return [
                    'course_id' => $course->course_id,
                    'course_name' => $course->course_name,
                    'score' => round((float) $course->score, 2),
                ];
            })->values();

            return [
                'student_id' => $studentUserId,
                'name' => $record->student?->name,
                'email' => $record->student?->email,
                'attendance_balance' => $record->student?->studentProfile?->attendance_balance,
                'at_risk_attendance' => $record->at_risk_attendance,
                'at_risk_grade' => $record->at_risk_grade,
                'failing_courses' => $failingCourses,
            ];
        });

        return response()->json($result);
    }
}
