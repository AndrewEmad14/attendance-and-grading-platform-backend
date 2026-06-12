<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\BillingRecord;
use App\Models\BusinessSession;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseDeliverable;
use App\Models\Engagement;
use App\Models\ExcuseRequest;
use App\Models\Lab;
use App\Models\LabGroup;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\Submission;
use App\Models\Tag;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tracks (3)
        $tracks = Track::factory(3)->create();

        // 2. Cohorts: 1 active + 3 inactive per track
        $cohorts = collect();
        foreach ($tracks as $track) {
            $cohorts->push(Cohort::factory()->active()->create(['track_id' => $track->id]));
            $cohorts = $cohorts->concat(
                Cohort::factory(3)->create(['track_id' => $track->id, 'is_active' => false])
            );
        }

        // 3. Lab groups (2 per cohort)
        $labGroups = collect();
        foreach ($cohorts as $cohort) {
            $labGroups = $labGroups->concat(
                LabGroup::factory(2)->create(['cohort_id' => $cohort->id])
            );
        }

        // 4. Students (30 per cohort, spread across lab groups)
        $students = collect();
        foreach ($cohorts as $cohort) {
            $cohortLabGroups = $labGroups->where('cohort_id', $cohort->id)->values();
            for ($i = 0; $i < 30; $i++) {
                $user = User::factory()->student()->create();
                $students->push(StudentProfile::factory()->create([
                    'user_id' => $user->id,
                    'cohort_id' => $cohort->id,
                    'lab_group_id' => $cohortLabGroups[$i % $cohortLabGroups->count()]->id,
                    'attendance_balance' => 250,
                ]));
            }
        }

        // 5. Staff — one branch manager, two track admins, seven instructors
        //    Each staff profile is stored with its role accessible via ->user->role
        $branchManagerUser = User::factory()->state(['role' => 'branch_manager'])->create();
        $branchManager = StaffProfile::factory()->create(['user_id' => $branchManagerUser->id]);

        $trackAdmins = collect();
        for ($i = 0; $i < 2; $i++) {
            $user = User::factory()->state(['role' => 'track_admin'])->create();
            $trackAdmins->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $instructors = collect();
        for ($i = 0; $i < 7; $i++) {
            $user = User::factory()->state(['role' => 'instructor'])->create();
            $instructors->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $allStaff = collect([$branchManager])->concat($trackAdmins)->concat($instructors);

        // 6. Assign BOTH track admins to every active cohort
        //    This is what makes track_admin access work via AccessService:
        //    student → cohort → cohorts_admins → staff_id
        foreach ($cohorts->where('is_active', true) as $cohort) {
            foreach ($trackAdmins as $admin) {
                DB::table('cohorts_admins')->insert([
                    'cohort_id' => $cohort->id,
                    'staff_id' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 7. Tags (10), randomly assigned to students
        $tags = Tag::factory(10)->create();
        foreach ($students as $student) {
            $student->tags()->attach($tags->random(rand(1, 3))->pluck('id'));
        }

        // 8. Courses (3 per cohort)
        $courses = collect();
        foreach ($cohorts as $cohort) {
            $courses = $courses->concat(
                Course::factory(3)->create(['cohort_id' => $cohort->id])
            );
        }

        // 9. Business sessions (5)
        $businessSessions = BusinessSession::factory(5)->create();

        // 10. Labs (2 per lab group, linked to a course in the same cohort)
        $labs = collect();
        foreach ($labGroups as $labGroup) {
            $course = $courses->where('cohort_id', $labGroup->cohort_id)->random();
            $labs = $labs->concat(
                Lab::factory(2)->create([
                    'lab_group_id' => $labGroup->id,
                    'course_id' => $course->id,
                ])
            );
        }

        // 11. Course deliverables (4 per course)
        $deliverables = collect();
        foreach ($courses as $course) {
            $deliverables = $deliverables->concat(
                CourseDeliverable::factory(4)->create(['course_id' => $course->id])
            );
        }

        // 12. Submissions
        $studentIds = $students->pluck('id')->toArray();
        $staffIds = $allStaff->pluck('id')->toArray();

        foreach ($deliverables as $deliverable) {
            $randomStudents = collect($studentIds)->random(min(10, count($studentIds)));
            foreach ($randomStudents as $studentId) {
                $isGraded = (bool) rand(0, 1);
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
        //
        //   - Course engagements  → track_admin or instructor as staff
        //   - Lab engagements     → instructor only
        //     (this is what makes instructor access work via AccessService:
        //      student → lab_group → labs → engagements → staff_id)
        //   - Business sessions   → any staff
        $engagements = collect();

        foreach ($courses as $course) {
            // Courses can be run by a track admin or an instructor
            $staffMember = collect([$trackAdmins->random(), $instructors->random()])->random();
            $engagements->push(
                Engagement::factory()->forEngageable($course)->create(['staff_id' => $staffMember->id])
            );
        }

        foreach ($labs as $lab) {
            // Labs are always run by an instructor — required for instructor access scope
            $engagements->push(
                Engagement::factory()->forEngageable($lab)->create(['staff_id' => $instructors->random()->id])
            );
        }

        foreach ($businessSessions as $bs) {
            $engagements->push(
                Engagement::factory()->forEngageable($bs)->create(['staff_id' => $allStaff->random()->id])
            );
        }

        // 14. Attach cohorts to business sessions
        //     Must happen before attendance calculations
        foreach ($businessSessions as $bs) {
            foreach ($cohorts->random(rand(1, 2)) as $cohort) {
                DB::table('business_sessions_cohorts')->insertOrIgnore([
                    'business_session_id' => $bs->id,
                    'cohort_id' => $cohort->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 15 & 16. Attendance records + excuse requests
        //          Reload engagements with their engageable relation
        $engagements = Engagement::with('engageable')->get();

        foreach ($engagements as $engagement) {
            $expectedStudentIds = collect($this->getExpectedStudentIds($engagement, $students));

            if ($expectedStudentIds->isEmpty()) {
                continue;
            }

            // 70% present, 30% absent
            $presentCount = max(1, (int) round($expectedStudentIds->count() * 0.7));
            $presentStudentIds = $expectedStudentIds->random($presentCount);
            $absentStudentIds = $expectedStudentIds->diff($presentStudentIds);

            foreach ($presentStudentIds as $studentId) {
                AttendanceRecord::factory()->create([
                    'engagement_id' => $engagement->id,
                    'student_id' => $studentId,
                    'arrived_at' => now()->subDays(rand(1, 30))->subHours(rand(1, 3)),
                    'left_at' => now()->subDays(rand(1, 30)),
                ]);
            }

            // ~50% of absent students file an excuse
            foreach ($absentStudentIds as $studentId) {
                if (rand(0, 1) === 0) {
                    continue;
                }

                $status = collect([
                    ExcuseRequest::STATUS_APPROVED,
                    ExcuseRequest::STATUS_REJECTED,
                    ExcuseRequest::STATUS_PENDING,
                ])->random();
                $isReviewed = in_array($status, [ExcuseRequest::STATUS_APPROVED, ExcuseRequest::STATUS_REJECTED]);

                ExcuseRequest::factory()->create([
                    'engagement_id' => $engagement->id,
                    'student_id' => $studentId,
                    'status' => $status,
                    'reviewed_by' => $isReviewed ? $allStaff->random()->id : null,
                    'reviewed_at' => $isReviewed ? now() : null,
                ]);
            }
        }

        // 17. attendance_balance starts at 250 for all students (set in step 4).
        //     The ProcessAbsences command is the sole source of truth for deductions.
        //     It runs after each session ends and is the only place that modifies the
        //     balance. Do NOT recalculate here — doing so would cause double-deduction
        //     when the command later processes the same sessions.

        // 18. Announcements
        Announcement::factory(5)->global()->create(['staff_id' => $allStaff->random()->id]);
        foreach ($cohorts as $cohort) {
            Announcement::factory(2)->forCohort($cohort->id)->create(['staff_id' => $allStaff->random()->id]);
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

        // 20. Named test users with tokens — each role can actually access
        //     students through the AccessService access chains
        $this->outputCredentials($trackAdmins, $instructors, $students, $labGroups, $engagements);
    }

    // --------------------------------------------------------------------
    // Credentials output
    //
    // branch_manager  — sees everything, no extra wiring needed
    // track_admin     — picks the first seeded track admin (already in cohorts_admins)
    // instructor      — picks the first seeded instructor and finds a student
    //                   whose lab group has an engagement owned by that instructor
    // student         — creates a fresh student in the first active cohort
    // --------------------------------------------------------------------
    private function outputCredentials(
        Collection $trackAdmins,
        Collection $instructors,
        Collection $students,
        Collection $labGroups,
        Collection $engagements,
    ): void {
        echo "\n".str_repeat('=', 60)."\n";
        echo "GENERATED TEST USERS, TOKENS & ACCESS MAP\n";
        echo str_repeat('=', 60)."\n\n";

        // Branch manager
        $bmUser = User::factory()->create(['email' => 'branch@example.com', 'role' => 'branch_manager', 'name' => 'Branch Manager']);
        $bmStaff = StaffProfile::factory()->create(['user_id' => $bmUser->id]);
        $bmToken = $bmUser->createToken('test-token')->plainTextToken;
        $sampleStudents = $students->take(3);

        echo "ROLE: branch_manager | NAME: {$bmUser->name} | USER ID: {$bmUser->id}\n";
        echo "TOKEN: {$bmToken}\n";
        echo "STAFF PROFILE ID: {$bmStaff->id}\n";
        echo "ACCESS: all students\n";
        echo "SAMPLE ACCESSIBLE STUDENTS:\n";
        foreach ($sampleStudents as $st) {
            echo "  - {$st->user->name} | student_id: {$st->id} | user_id: {$st->user->id}\n";
        }
        echo str_repeat('-', 60)."\n";

        // Track admin — reuse first seeded track admin user so cohorts_admins is already set
        $taStaff = $trackAdmins->first();
        $taUser = $taStaff->user;
        $taUser->update(['email' => 'admin@example.com', 'name' => 'Track Admin']);
        $taToken = $taUser->createToken('test-token')->plainTextToken;

        // Students accessible: those in cohorts where this track admin is assigned
        $adminCohortIds = DB::table('cohorts_admins')->where('staff_id', $taStaff->id)->pluck('cohort_id');
        $accessibleStudents = $students->whereIn('cohort_id', $adminCohortIds->toArray())->take(3);

        echo "ROLE: track_admin | NAME: {$taUser->name} | USER ID: {$taUser->id}\n";
        echo "TOKEN: {$taToken}\n";
        echo "STAFF PROFILE ID: {$taStaff->id}\n";
        echo 'ASSIGNED COHORT IDS: '.$adminCohortIds->implode(', ')."\n";
        echo "SAMPLE ACCESSIBLE STUDENTS:\n";
        foreach ($accessibleStudents as $st) {
            echo "  - {$st->user->name} | student_id: {$st->id} | cohort_id: {$st->cohort_id}\n";
        }
        echo str_repeat('-', 60)."\n";

        // Instructor — reuse first seeded instructor; find a student reachable via
        // student → lab_group → labs → engagements → staff_id = this instructor
        $instrStaff = $instructors->first();
        $instrUser = $instrStaff->user;
        $instrUser->update(['email' => 'instructor@example.com', 'name' => 'Instructor']);
        $instrToken = $instrUser->createToken('test-token')->plainTextToken;

        $instrEngagements = $engagements->where('staff_id', $instrStaff->id);
        $instrLabIds = $instrEngagements
            ->filter(fn ($e) => $e->engageable_type === Engagement::TYPE_LAB)
            ->pluck('engageable_id');
        $instrLabGroupIds = $labGroups->whereIn(
            'id',
            Lab::whereIn('id', $instrLabIds)->pluck('lab_group_id')->toArray()
        )->pluck('id');
        $instrStudents = $students->whereIn('lab_group_id', $instrLabGroupIds->toArray())->take(3);

        echo "ROLE: instructor | NAME: {$instrUser->name} | USER ID: {$instrUser->id}\n";
        echo "TOKEN: {$instrToken}\n";
        echo "STAFF PROFILE ID: {$instrStaff->id}\n";
        echo 'OWNS ENGAGEMENTS ON LAB IDS: '.$instrLabIds->implode(', ')."\n";
        echo "SAMPLE ACCESSIBLE STUDENTS (via lab_group → labs → engagements):\n";
        foreach ($instrStudents as $st) {
            echo "  - {$st->user->name} | student_id: {$st->id} | lab_group_id: {$st->lab_group_id}\n";
        }
        echo str_repeat('-', 60)."\n";

        // Student
        $stUser = User::factory()->student()->create(['email' => 'student@example.com', 'name' => 'Test Student']);
        $stProfile = StudentProfile::factory()->create(['user_id' => $stUser->id]);
        $stToken = $stUser->createToken('test-token')->plainTextToken;

        echo "ROLE: student | NAME: {$stUser->name} | USER ID: {$stUser->id}\n";
        echo "TOKEN: {$stToken}\n";
        echo "STUDENT PROFILE ID: {$stProfile->id}\n";
        echo "ACCESS: own records only\n";
        echo str_repeat('-', 60)."\n";
    }

    // --------------------------------------------------------------------
    // Helpers
    // --------------------------------------------------------------------

    private function getExpectedStudentIds(Engagement $engagement, Collection $students): array
    {
        $engageable = $engagement->engageable;
        if (! $engageable) {
            return [];
        }

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

    private function getExpectedEngagementIdsForStudent(StudentProfile $student, Collection $engagements): array
    {
        $ids = [];

        foreach ($engagements as $engagement) {
            $engageable = $engagement->engageable;
            if (! $engageable) {
                continue;
            }

            $belongs = match ($engagement->engageable_type) {
                Engagement::TYPE_COURSE => $engageable->cohort_id === $student->cohort_id,

                Engagement::TYPE_LAB => $engageable->lab_group_id === $student->lab_group_id,

                Engagement::TYPE_BUSINESS_SESSION => DB::table('business_sessions_cohorts')
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
