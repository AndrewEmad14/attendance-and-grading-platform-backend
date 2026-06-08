<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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

        // 3. Lab groups (2 per cohort) - created before students so student profiles can reference them
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
                'lab_group_id' => $cohortLabGroups->random()->id
            ]));
        }

        // 5. Create staff profiles with correct roles
        $staff = collect();

        // One branch_manager
        $branchManagerUser = User::factory()->branchManager()->create();
        $staff->push(StaffProfile::factory()->create(['user_id' => $branchManagerUser->id]));

        // Two track_admins
        $trackAdminUsers = User::factory(2)->trackAdmin()->create();
        foreach ($trackAdminUsers as $user) {
            $staff->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        // Seven instructors
        $instructorUsers = User::factory(7)->instructor()->create();
        foreach ($instructorUsers as $user) {
            $staff->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        // 6. Assign track admins to active cohorts (LC-2)
        $trackAdminStaff = $staff->filter(function ($staffProfile) {
            return $staffProfile->user->role === 'track_admin';
        }); // Should give 2 staff profiles

        foreach ($cohorts as $cohort) {
            if ($cohort->is_active) {
                // Assign all track admins to each active cohort
                $cohort->trackAdmins()->attach($trackAdminStaff->pluck('id')->toArray());
            } else {
                // Optionally assign only one track admin to inactive cohorts (or none)
                // Uncomment next line if you want to assign the first track admin to all cohorts
                // $cohort->trackAdmins()->attach($trackAdminStaff->first()->id);
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

        // 12. Submissions (for 60% of student–deliverable pairs)
        $studentIds = $students->pluck('id')->toArray();
        $staffIds = $staff->pluck('id')->toArray();
        foreach ($deliverables as $deliverable) {
            $randomStudents = collect($studentIds)->random(min(10, count($studentIds)));
            foreach ($randomStudents as $studentId) {
                $isGraded = rand(0, 1);
                $isOverridden = $isGraded && (rand(0, 9) === 0);

                $gradedBy = $isGraded ? collect($staffIds)->random() : null;
                $overridenBy = $isOverridden ? collect($staffIds)->random() : null;

                Submission::factory()->create([
                    'deliverable_id' => $deliverable->id,
                    'student_id' => $studentId,
                    'graded_by' => $gradedBy,
                    'override_score' => $isOverridden ? rand(0, 100) : null,
                    'overriden_by' => $overridenBy,
                    'override_note' => $isOverridden ? 'Grade adjusted after review.' : null,
                    'overrided_at' => $isOverridden ? now() : null,
                ]);
            }
        }

        // 13. Engagements
        // Lectures (2 per course)
        foreach ($courses as $course) {
            Engagement::factory(2)->forEngageable($course)->create(['staff_id' => $staff->random()->id]);
        }
        // Labs (1 per lab)
        foreach ($labs as $lab) {
            Engagement::factory()->forEngageable($lab)->create(['staff_id' => $staff->random()->id]);
        }
        // Business sessions (1 per session)
        foreach ($businessSessions as $bs) {
            Engagement::factory()->forEngageable($bs)->create(['staff_id' => $staff->random()->id]);
        }

        // 14. Attendance records – BULK INSERT (fast)
        $engagements = Engagement::all();
        $studentIds = $students->pluck('id')->toArray();
        $studentCount = count($studentIds);
        $attendanceData = [];

        foreach ($engagements as $engagement) {
            $presentCount = rand(ceil($studentCount * 0.6), $studentCount);

            // Pick random student IDs
            $presentKeys = (array) array_rand($studentIds, $presentCount);
            $presentStudentIds = array_map(fn($key) => $studentIds[$key], $presentKeys);

            foreach ($presentStudentIds as $studentId) {
                $attendanceData[] = [
                    'engagement_id' => $engagement->id,
                    'student_id' => $studentId,
                    'arrived_at' => now()->subDays(rand(0, 30))->subHours(rand(1, 8))->toDateTimeString(),
                    'left_at' => now()->subDays(rand(0, 30))->toDateTimeString(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Flush to Supabase immediately when we hit 1,000 records to save memory
                if (count($attendanceData) >= 1000) {
                    DB::table('attendance_records')->insert($attendanceData);
                    $attendanceData = []; // Wipe the array to free up RAM
                }
            }
        }

        // Insert any leftover records remaining in the array
        if (!empty($attendanceData)) {
            DB::table('attendance_records')->insert($attendanceData);
        }

        // 15. Excuse requests (10% of attendance records)
        $attendanceRecords = DB::table('attendance_records')->pluck('id')->toArray();
        $excuseCount = (int)(count($attendanceRecords) * 0.1);
        $selectedIds = (array) array_rand($attendanceRecords, $excuseCount);
        foreach ($selectedIds as $attendanceId) {
            ExcuseRequest::factory()->create(['attendance_id' => $attendanceId]);
        }

        // 16. Attach cohorts to business sessions
        foreach ($businessSessions as $bs) {
            $randomCohorts = $cohorts->random(rand(1, 2));
            $bs->cohorts()->attach($randomCohorts->pluck('id'));
        }

        // 17. Announcements – fix staff_id extraction
        // 5 global announcements
        Announcement::factory(5)->global()->create([
            'staff_id' => $staff->random()->id,
        ]);
        // 2 per cohort
        foreach ($cohorts as $cohort) {
            Announcement::factory(2)->forCohort($cohort->id)->create([
                'staff_id' => $staff->random()->id,
            ]);
        }

        // 18. Billing records
        foreach ($engagements as $engagement) {
            BillingRecord::factory()->create([
                'engagement_id' => $engagement->id,
                'staff_id' => $engagement->staff_id,
                'delivered_hours' => $engagement->scheduled_hours,
                'total_amount' => $engagement->scheduled_hours * 150, // e.g. 150 per hour
            ]);
        }
    }
}
