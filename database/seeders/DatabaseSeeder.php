<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Models\Track;
use App\Models\Cohort;
use App\Models\StudentProfile;
use App\Models\StaffProfile;
use App\Models\Tag;
use App\Models\LabGroup;
use App\Models\Course;
use App\Models\BusinessSession;
use App\Models\Lab;
use App\Models\CourseDeliverable;
use App\Models\Engagement;
use App\Models\ExcuseRequest;
use App\Models\Submission;
use App\Models\Announcement;
use App\Models\User;
use App\Models\BillingRecord;
use App\Models\AttendanceRecord;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // 1. Tracks (3)
        $tracks = Track::factory(3)->create();

        // 2. Cohorts: 1 active + 3 inactive per track
        $cohorts = collect();
        foreach ($tracks as $track) {
            $cohorts->push(Cohort::factory()->active()->create(['track_id' => $track->id]));
            $cohorts = $cohorts->concat(Cohort::factory(3)->create(['track_id' => $track->id, 'is_active' => false]));
        }

        // 3. Lab groups (2 per cohort)
        $labGroups = collect();
        foreach ($cohorts as $cohort) {
            $labGroups = $labGroups->concat(LabGroup::factory(2)->create(['cohort_id' => $cohort->id]));
        }

        // 4. Student profiles (30 per cohort)
        $students = collect();
        foreach ($cohorts as $cohort) {
            $cohortLabGroups = $labGroups->where('cohort_id', $cohort->id);
            $students = $students->concat(StudentProfile::factory(30)->create([
                'cohort_id' => $cohort->id,
                'lab_group_id' => $cohortLabGroups->random()->id,
                'attendance_balance' => 250,
            ]));
        }

        // 5. Staff profiles
        $staff = collect();

        $branchManagerUser = User::factory()->branchManager()->create();
        $staff->push(StaffProfile::factory()->create(['user_id' => $branchManagerUser->id]));

        $trackAdminUsers = User::factory(2)->trackAdmin()->create();
        foreach ($trackAdminUsers as $user) {
            $staff->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $instructorUsers = User::factory(7)->instructor()->create();
        foreach ($instructorUsers as $user) {
            $staff->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        // 6. Assign track admins to active cohorts
        $trackAdminStaff = $staff->filter(fn($s) => $s->user->role === 'track_admin');
        foreach ($cohorts as $cohort) {
            if ($cohort->is_active) {
                DB::table('cohorts_admins')->insert(
                    $trackAdminStaff->map(fn($admin) => [
                        'cohort_id' => $cohort->id,
                        'staff_id' => $admin->id
                    ])->toArray()
                );
            }
        }

        // 7. Tags (10)
        $tags = Tag::factory(10)->create();
        foreach ($students as $student) {
            $student->tags()->attach($tags->random(rand(1, 3))->pluck('id'));
        }

        // 8. Courses (3 per cohort)
        $courses = collect();
        foreach ($cohorts as $cohort) {
            $courses = $courses->concat(Course::factory(3)->create(['cohort_id' => $cohort->id]));
        }

        // 9. Business sessions (5)
        $businessSessions = BusinessSession::factory(5)->create();

        // 10. Labs (2 per lab group)
        $labs = collect();
        foreach ($labGroups as $labGroup) {
            $course = $courses->where('cohort_id', $labGroup->cohort_id)->random();
            $labs = $labs->concat(Lab::factory(2)->create([
                'lab_group_id' => $labGroup->id,
                'course_id' => $course->id,
            ]));
        }

        // 11. Course deliverables (4 per course)
        $deliverables = collect();
        foreach ($courses as $course) {
            $deliverables = $deliverables->concat(CourseDeliverable::factory(4)->create(['course_id' => $course->id]));
        }

        // 12. Submissions
        $studentIds = $students->pluck('id')->toArray();
        $staffIds = $staff->pluck('id')->toArray();
        foreach ($deliverables as $deliverable) {
            $randomStudents = collect($studentIds)->random(min(10, count($studentIds)));
            foreach ($randomStudents as $studentId) {
                $isGraded = rand(0, 1);
                $isOverridden = $isGraded && (rand(0, 9) === 0);

                Submission::factory()->create([
                    'deliverable_id' => $deliverable->id,
                    'student_id' => $studentId,
                    'graded_by' => $isGraded ? collect($staffIds)->random() : null,
                    'override_score' => $isOverridden ? rand(0, 100) : null,
                    'overridden_by' => $isOverridden ? collect($staffIds)->random() : null,
                    'override_note' => $isOverridden ? 'Grade adjusted after review.' : null,
                    'overridden_at' => $isOverridden ? now() : null,
                ]);
            }
        }

        // 13. Engagements
        $engagements = collect();
        foreach ($courses as $course) {
            $engagements->push(Engagement::factory()->forEngageable($course)->create(['staff_id' => $staff->random()->id]));
        }
        foreach ($labs as $lab) {
            $engagements->push(Engagement::factory()->forEngageable($lab)->create(['staff_id' => $staff->random()->id]));
        }
        foreach ($businessSessions as $bs) {
            $engagements->push(Engagement::factory()->forEngageable($bs)->create(['staff_id' => $staff->random()->id]));
        }

        // 14. Attach cohorts to business sessions (Must happen before attendance calculations)
        foreach ($businessSessions as $bs) {
            $randomCohorts = $cohorts->random(rand(1, 2));
            // Assuming pivot table 'business_sessions_cohorts' based on your previous logic
            foreach ($randomCohorts as $cohort) {
                DB::table('business_sessions_cohorts')->insert([
                    'business_session_id' => $bs->id,
                    'cohort_id' => $cohort->id
                ]);
            }
        }

        // 15. Attendance Records & 16. Excuse Requests
        // Fetch all created engagements as an Eloquent Collection with relations loaded
        $engagements = Engagement::with('engageable')->get();

        foreach ($engagements as $engagement) {
            $expectedStudentIds = collect($this->getExpectedStudentIds($engagement, $students));

            if ($expectedStudentIds->isEmpty()) continue;

            // 70% present, 30% absent
            $presentCount = max(1, (int) round($expectedStudentIds->count() * 0.7));
            $presentStudentIds = $expectedStudentIds->random($presentCount);
            $absentStudentIds = $expectedStudentIds->diff($presentStudentIds);

            // Create Attendance Records ONLY for the present students
            foreach ($presentStudentIds as $studentId) {
                AttendanceRecord::factory()->create([
                    'engagement_id' => $engagement->id,
                    'student_id'    => $studentId,
                    'arrived_at'    => now()->subDays(rand(1, 30))->subHours(rand(1, 3)),
                    'left_at'       => now()->subDays(rand(1, 30)),
                ]);
            }

            // Create Excuse Requests for roughly 50% of the ABSENT students
            foreach ($absentStudentIds as $studentId) {
                if (rand(0, 1) === 1) { // 50% chance
                    $status = collect([
                        ExcuseRequest::STATUS_APPROVED,
                        ExcuseRequest::STATUS_REJECTED,
                        ExcuseRequest::STATUS_PENDING
                    ])->random();

                    $isReviewed = in_array($status, [ExcuseRequest::STATUS_APPROVED, ExcuseRequest::STATUS_REJECTED]);

                    ExcuseRequest::factory()->create([
                        'engagement_id' => $engagement->id,
                        'student_id'    => $studentId,
                        'status'        => $status,
                        'reviewed_by'   => $isReviewed ? $staff->random()->id : null,
                        'reviewed_at'   => $isReviewed ? now() : null,
                    ]);
                }
            }
        }

        // 17. Recalculate attendance_balance for every student
        // Using Eloquent instead of raw DB inserts ensures everything syncs cleanly
        foreach ($students as $student) {
            $expectedEngagementIds = $this->getExpectedEngagementIdsForStudent($student, $engagements);

            if (empty($expectedEngagementIds)) continue;

            // Find engagements where the student ACTUALLY has an attendance record
            $attendedEngagementIds = AttendanceRecord::where('student_id', $student->id)
                ->whereNotNull('arrived_at')
                ->pluck('engagement_id')
                ->toArray();

            $absentEngagementIds = array_diff($expectedEngagementIds, $attendedEngagementIds);

            // Count approved excuses among the absences
            $approvedExcusesCount = ExcuseRequest::where('student_id', $student->id)
                ->whereIn('engagement_id', $absentEngagementIds)
                ->where('status', ExcuseRequest::STATUS_APPROVED)
                ->count();

            $unexcusedCount = count($absentEngagementIds) - $approvedExcusesCount;
            $newBalance = 250 - ($unexcusedCount * 25) - ($approvedExcusesCount * 5);

            $student->update(['attendance_balance' => $newBalance]);
        }

        // Mark ended engagements as processed
        Engagement::where('ends_at', '<=', now())->update(['absences_processed_at' => now()]);

        // 18. Announcements
        Announcement::factory(5)->global()->create(['staff_id' => $staff->random()->id]);
        foreach ($cohorts as $cohort) {
            Announcement::factory(2)->forCohort($cohort->id)->create(['staff_id' => $staff->random()->id]);
        }

        // 19. Billing records
        foreach ($engagements as $engagement) {
            BillingRecord::factory()->create([
                'engagement_id' => $engagement->id,
                'staff_id' => $engagement->staff_id,
                'delivered_hours' => $engagement->scheduled_hours,
                'total_amount' => $engagement->scheduled_hours * 150,
            ]);
        }
    }

    /**
     * Determines which students are EXPECTED to attend an engagement.
     */
    private function getExpectedStudentIds(Engagement $engagement, Collection $students): array
    {
        $engageable = $engagement->engageable;

        if (!$engageable) return [];

        return match ($engagement->engageable_type) {
            Engagement::TYPE_COURSE => $students
                ->where('cohort_id', $engageable->cohort_id)
                ->pluck('id')
                ->toArray(),

            Engagement::TYPE_LAB => $students
                ->where('lab_group_id', $engageable->lab_group_id)
                ->pluck('id')
                ->toArray(),

            Engagement::TYPE_BUSINESS_SESSION => $students
                ->whereIn(
                    'cohort_id',
                    DB::table('business_sessions_cohorts')
                        ->where('business_session_id', $engageable->id)
                        ->pluck('cohort_id')
                        ->toArray()
                )
                ->pluck('id')
                ->toArray(),

            default => [],
        };
    }

    /**
     * Determines which engagements a specific student is EXPECTED to attend.
     */
    private function getExpectedEngagementIdsForStudent(StudentProfile $student, Collection $engagements): array
    {
        $ids = [];

        foreach ($engagements as $engagement) {
            $engageable = $engagement->engageable;
            if (!$engageable) continue;

            $belongs = match ($engagement->engageable_type) {
                Engagement::TYPE_COURSE =>
                $engageable->cohort_id === $student->cohort_id,

                Engagement::TYPE_LAB =>
                $engageable->lab_group_id === $student->lab_group_id,

                Engagement::TYPE_BUSINESS_SESSION =>
                DB::table('business_sessions_cohorts')
                    ->where('business_session_id', $engageable->id)
                    ->where('cohort_id', $student->cohort_id)
                    ->exists(),

                default => false,
            };

            if ($belongs) {
                $ids[] = $engagement->id;
            }
        }

        return $ids;
    }
}
