<?php

namespace Database\Seeders;

use App\Models\Announcement;
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
use App\Models\Tag;
use App\Models\Track;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    const LAB_GROUPS_PER_COHORT = 3;

    const STUDENTS_PER_COHORT = 20;

    const COURSES_PER_COHORT = 4;

    const LABS_PER_LAB_GROUP = 2;

    const DELIVERABLES_PER_COURSE = 3;

    const BUSINESS_SESSIONS_TOTAL = 5;

    const COHORT_YEAR_MAP = [
        1 => 2025, // past / inactive
        2 => 2026, // current / active
    ];

    const DAILY_SLOTS = [
        0 => ['09:00', '11:00', 2],
        1 => ['11:30', '13:30', 2],
        2 => ['14:30', '17:30', 3],
    ];

    public function run(): void
    {
        $this->command->info('Starting seed...');
        DB::statement('SET session_replication_role = replica;');

        // -----------------------------------------------------------------------
        // 1. Tags
        // -----------------------------------------------------------------------
        $tags = Tag::factory(10)->create();

        // -----------------------------------------------------------------------
        // 2. Staff
        // -----------------------------------------------------------------------
        $this->command->info('Creating staff...');

        $branchManagers = collect();
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->branchManager()->create([
                'name' => fake()->name(),
                'email' => 'branch_manager_'.($i + 1).'@iti.gov.eg',
            ]);
            $branchManagers->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $trackAdmins = collect();
        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->trackAdmin()->create();
            $trackAdmins->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $instructors = collect();
        for ($i = 0; $i < 20; $i++) {
            $user = User::factory()->instructor()->create();
            $instructors->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $allStaff = $branchManagers->concat($trackAdmins)->concat($instructors);
        $staffIds = $allStaff->pluck('id')->toArray();
        $instrIds = $instructors->pluck('id')->toArray();
        $taIds = $trackAdmins->pluck('id')->toArray();

        // -----------------------------------------------------------------------
        // 3. Tracks (exactly 2)
        // -----------------------------------------------------------------------
        $this->command->info('Creating 2 tracks...');
        $tracks = Track::factory(2)->create();

        // -----------------------------------------------------------------------
        // 4. Cohorts — 2 per track (cohort 1 = 2025 inactive, cohort 2 = 2026 active)
        // -----------------------------------------------------------------------
        $this->command->info('Creating cohorts...');
        $cohorts = collect();
        foreach ($tracks as $track) {
            foreach (self::COHORT_YEAR_MAP as $num => $year) {
                $isActive = ($num === 2);
                $cohorts->push(Cohort::factory()->create([
                    'track_id' => $track->id,
                    'number' => $num,
                    'is_active' => $isActive,
                ]));
            }
        }

        $activeCohorts = $cohorts->where('is_active', true)->values();
        $inactiveCohorts = $cohorts->where('is_active', false)->values();

        // -----------------------------------------------------------------------
        // 5. Assign track admins to active cohorts
        // -----------------------------------------------------------------------
        $this->command->info('Assigning track admins...');
        $taCollection = $trackAdmins->values();
        $taCount = $taCollection->count();
        $insertRows = [];

        foreach ($activeCohorts as $idx => $cohort) {
            $admin1 = $taCollection[$idx % $taCount];
            $admin2 = $taCollection[($idx + 1) % $taCount];
            foreach ([$admin1, $admin2] as $admin) {
                $insertRows[] = [
                    'cohort_id' => $cohort->id,
                    'staff_id' => $admin->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('cohorts_admins')->insertOrIgnore($insertRows);

        // -----------------------------------------------------------------------
        // 6. Lab groups
        // -----------------------------------------------------------------------
        $this->command->info('Creating lab groups...');
        $labGroups = collect();
        $lgNames = ['Alpha', 'Beta', 'Gamma'];
        foreach ($cohorts as $cohort) {
            for ($g = 0; $g < self::LAB_GROUPS_PER_COHORT; $g++) {
                $labGroups->push(LabGroup::factory()->create([
                    'cohort_id' => $cohort->id,
                    'name' => 'Group '.$lgNames[$g],
                ]));
            }
        }

        // -----------------------------------------------------------------------
        // 7. Students
        // -----------------------------------------------------------------------
        $this->command->info('Creating students...');
        $students = collect();
        foreach ($cohorts as $cohort) {
            $cohortLabGroups = $labGroups->where('cohort_id', $cohort->id)->values();
            $lgCount = $cohortLabGroups->count();

            for ($i = 0; $i < self::STUDENTS_PER_COHORT; $i++) {
                $user = User::factory()->student()->create();
                $students->push(StudentProfile::factory()->create([
                    'user_id' => $user->id,
                    'cohort_id' => $cohort->id,
                    'lab_group_id' => $cohortLabGroups[$i % $lgCount]->id,
                    'attendance_balance' => 250,
                ]));
            }
        }

        foreach ($students->random((int) ($students->count() * 0.4)) as $student) {
            $student->tags()->attach($tags->random(rand(1, 3))->pluck('id'));
        }

        // -----------------------------------------------------------------------
        // 8. Courses
        // -----------------------------------------------------------------------
        $this->command->info('Creating courses...');
        $courses = collect();
        $courseNamePool = [
            'Introduction to Programming',
            'Object-Oriented Programming',
            'Database Design & SQL',
            'Web Fundamentals',
            'JavaScript Essentials',
            'Backend Development',
            'Linux & Command Line',
            'Software Engineering Principles',
        ];

        foreach ($cohorts as $cohort) {
            shuffle($courseNamePool);
            for ($c = 0; $c < self::COURSES_PER_COHORT; $c++) {
                $courses->push(Course::factory()->create([
                    'cohort_id' => $cohort->id,
                    'name' => $courseNamePool[$c],
                ]));
            }
        }

        // -----------------------------------------------------------------------
        // 9. Labs
        // -----------------------------------------------------------------------
        $this->command->info('Creating labs...');
        $labs = collect();
        $labNames = [
            'CRUD Application Lab',
            'REST API Lab',
            'Database Schema Lab',
            'Unit Testing Lab',
            'Frontend Component Lab',
            'Git Workflow Lab',
        ];

        foreach ($labGroups as $labGroup) {
            $cohortCourses = $courses->where('cohort_id', $labGroup->cohort_id)->values();
            if ($cohortCourses->isEmpty()) {
                continue;
            }
            shuffle($labNames);
            for ($l = 0; $l < self::LABS_PER_LAB_GROUP; $l++) {
                $labs->push(Lab::factory()->create([
                    'lab_group_id' => $labGroup->id,
                    'course_id' => $cohortCourses->random()->id,
                    'name' => $labNames[$l],
                ]));
            }
        }

        // -----------------------------------------------------------------------
        // 10. Business sessions
        // -----------------------------------------------------------------------
        $this->command->info('Creating business sessions...');
        $businessSessions = BusinessSession::factory(self::BUSINESS_SESSIONS_TOTAL)->create();

        // -----------------------------------------------------------------------
        // 11. Deliverables
        // -----------------------------------------------------------------------
        $this->command->info('Creating deliverables...');
        $deliverables = collect();
        foreach ($courses as $course) {
            $deliverables = $deliverables->concat(
                CourseDeliverable::factory(self::DELIVERABLES_PER_COURSE)->create(['course_id' => $course->id])
            );
        }

        // -----------------------------------------------------------------------
        // 12. Engagements
        // -----------------------------------------------------------------------
        $this->command->info('Creating engagements...');
        $slotUsage = [];
        $engagementRows = [];

        $assignStaff = function (string $type) use ($instrIds, $taIds) {
            if ($type === 'lab') {
                return $instrIds[array_rand($instrIds)];
            }
            if ($type === 'course') {
                $pool = array_merge($instrIds, $taIds);

                return $pool[array_rand($pool)];
            }

            return $instrIds[array_rand($instrIds)];
        };

        foreach ($cohorts as $cohort) {
            $year = self::COHORT_YEAR_MAP[$cohort->number];
            $yearStart = Carbon::create($year, 1, 15);
            $yearEnd = Carbon::create($year, 11, 30);

            $weekdays = collect();
            $current = $yearStart->copy();
            while ($current->lte($yearEnd)) {
                if ($current->isWeekday()) {
                    $weekdays->push($current->copy());
                }
                $current->addDay();
            }

            $cohortCourses = $courses->where('cohort_id', $cohort->id)->values();
            $cohortLabGroups = $labGroups->where('cohort_id', $cohort->id)->values();
            $cohortLabs = $labs->filter(
                fn ($l) => $cohortLabGroups->pluck('id')->contains($l->lab_group_id)
            )->values();

            foreach ($cohortCourses as $course) {
                $sessionDates = $weekdays->random(min(15, $weekdays->count()))->sortBy(fn ($d) => $d->timestamp);
                foreach ($sessionDates as $date) {
                    $freeSlot = $this->findFreeSlot($slotUsage, $cohort->id, $date->toDateString());
                    if ($freeSlot === null) {
                        continue;
                    }
                    $slotUsage["{$cohort->id}:{$date->toDateString()}:{$freeSlot}"] = true;
                    [$startTime, $endTime, $hours] = self::DAILY_SLOTS[$freeSlot];
                    $startsAt = $date->copy()->setTimeFromTimeString($startTime);
                    $endsAt = $date->copy()->setTimeFromTimeString($endTime);
                    $engagementRows[] = [
                        'engageable_type' => Course::class,
                        'engageable_id' => $course->id,
                        'staff_id' => $assignStaff('course'),
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'scheduled_hours' => $hours,
                        'absences_processed_at' => $endsAt->isPast() ? now() : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            foreach ($cohortLabGroups as $labGroup) {
                $groupLabs = $cohortLabs->where('lab_group_id', $labGroup->id)->values();
                foreach ($groupLabs as $lab) {
                    $sessionDates = $weekdays->random(min(8, $weekdays->count()))->sortBy(fn ($d) => $d->timestamp);
                    foreach ($sessionDates as $date) {
                        $freeSlot = $this->findFreeSlot($slotUsage, "lg_{$labGroup->id}", $date->toDateString());
                        if ($freeSlot === null) {
                            continue;
                        }
                        $slotUsage["lg_{$labGroup->id}:{$date->toDateString()}:{$freeSlot}"] = true;
                        [$startTime, $endTime, $hours] = self::DAILY_SLOTS[$freeSlot];
                        $startsAt = $date->copy()->setTimeFromTimeString($startTime);
                        $endsAt = $date->copy()->setTimeFromTimeString($endTime);
                        $engagementRows[] = [
                            'engageable_type' => Lab::class,
                            'engageable_id' => $lab->id,
                            'staff_id' => $assignStaff('lab'),
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt,
                            'scheduled_hours' => $hours,
                            'absences_processed_at' => $endsAt->isPast() ? now() : null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        // Business sessions span both years
        $bsWeekdays = collect();
        $current = Carbon::create(2025, 1, 1);
        $bsEnd = Carbon::create(2026, 12, 31);
        while ($current->lte($bsEnd)) {
            if ($current->isWeekday()) {
                $bsWeekdays->push($current->copy());
            }
            $current->addDay();
        }

        foreach ($businessSessions as $bs) {
            /** @var Carbon $date */
            $date = $bsWeekdays->random();
            $slot = rand(0, 2);
            [$startTime, $endTime, $hours] = self::DAILY_SLOTS[$slot];
            $startsAt = $date->copy()->setTimeFromTimeString($startTime);
            $endsAt = $date->copy()->setTimeFromTimeString($endTime);

            $engagementRows[] = [
                'engageable_type' => BusinessSession::class,
                'engageable_id' => $bs->id,
                'staff_id' => $staffIds[array_rand($staffIds)],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'scheduled_hours' => $hours,
                'absences_processed_at' => $endsAt->isPast() ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $selectedCohorts = $cohorts->random(rand(1, $cohorts->count()));
            foreach ($selectedCohorts as $cohort) {
                DB::table('business_sessions_cohorts')->insertOrIgnore([
                    'business_session_id' => $bs->id,
                    'cohort_id' => $cohort->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->command->info('Inserting '.count($engagementRows).' engagements...');
        foreach (array_chunk($engagementRows, 500) as $chunk) {
            DB::table('engagements')->insert($chunk);
        }

        $engagements = Engagement::with('engageable')->get();

        // -----------------------------------------------------------------------
        // 13. Submissions
        // -----------------------------------------------------------------------
        $this->command->info('Creating submissions...');
        $studentIdsByCohort = $students->groupBy('cohort_id');
        $submissionRows = [];

        foreach ($deliverables as $deliverable) {
            $cohortStudents = $studentIdsByCohort->get($deliverable->course->cohort_id ?? 0, collect());
            if ($cohortStudents->isEmpty()) {
                continue;
            }

            $sample = $cohortStudents->random(min(15, $cohortStudents->count()));
            foreach ($sample as $student) {
                $isGraded = rand(0, 100) < 75;
                $isOverridden = $isGraded && rand(0, 100) < 8;

                $submissionRows[] = [
                    'deliverable_id' => $deliverable->id,
                    'student_id' => $student->id,
                    'submission_type' => fake()->randomElement(['file', 'link']),
                    'submission_path' => 'https://github.com/student/'.fake()->word().'-project',
                    'raw_score' => round(fake()->randomFloat(1, $deliverable->max_score * 0.3, $deliverable->max_score), 1),
                    'graded_by' => $isGraded ? $staffIds[array_rand($staffIds)] : null,
                    'override_score' => $isOverridden ? round(fake()->randomFloat(1, $deliverable->max_score * 0.5, $deliverable->max_score), 1) : null,
                    'overridden_by' => $isOverridden ? $staffIds[array_rand($staffIds)] : null,
                    'override_note' => $isOverridden ? 'Grade adjusted after review.' : null,
                    'overridden_at' => $isOverridden ? now()->subDays(rand(1, 30)) : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach (array_chunk($submissionRows, 500) as $chunk) {
            DB::table('submissions')->insert($chunk);
        }

        // -----------------------------------------------------------------------
        // 14. Attendance records + excuse requests
        // -----------------------------------------------------------------------
        $this->command->info('Creating attendance records...');

        $pastEngagements = $engagements->filter(fn ($e) => $e->ends_at?->isPast());
        $studentsByCohort = $students->groupBy('cohort_id')->map(fn ($g) => $g->pluck('id')->toArray());
        $studentsByLabGroup = $students->groupBy('lab_group_id')->map(fn ($g) => $g->pluck('id')->toArray());
        $bsCohortMap = DB::table('business_sessions_cohorts')->get()
            ->groupBy('business_session_id')
            ->map(fn ($rows) => $rows->pluck('cohort_id')->toArray());

        $attendanceRows = [];
        $excuseRows = [];

        foreach ($pastEngagements as $engagement) {
            $studentIds = $this->resolveExpectedStudentIds($engagement, $studentsByCohort, $studentsByLabGroup, $bsCohortMap);
            if (empty($studentIds)) {
                continue;
            }

            $presentCount = max(1, (int) round(count($studentIds) * 0.75));
            $shuffled = $studentIds;
            shuffle($shuffled);
            $presentIds = array_slice($shuffled, 0, $presentCount);
            $absentIds = array_slice($shuffled, $presentCount);

            $engStart = $engagement->starts_at;
            $engEnd = $engagement->ends_at;

            foreach ($presentIds as $studentId) {
                $attendanceRows[] = [
                    'engagement_id' => $engagement->id,
                    'student_id' => $studentId,
                    'arrived_at' => $engStart->copy()->addMinutes(rand(0, 15)),
                    'left_at' => $engEnd->copy()->subMinutes(rand(0, 10)),
                    'created_at' => $engStart,
                    'updated_at' => $engStart,
                ];
            }

            foreach ($absentIds as $studentId) {
                if (rand(0, 100) > 55) {
                    continue;
                }
                $statusRoll = rand(0, 100);
                $status = match (true) {
                    $statusRoll < 45 => ExcuseRequest::STATUS_APPROVED,
                    $statusRoll < 75 => ExcuseRequest::STATUS_REJECTED,
                    default => ExcuseRequest::STATUS_PENDING,
                };
                $isReviewed = in_array($status, [ExcuseRequest::STATUS_APPROVED, ExcuseRequest::STATUS_REJECTED]);
                $excuseRows[] = [
                    'engagement_id' => $engagement->id,
                    'student_id' => $studentId,
                    'reason' => fake()->randomElement([
                        'Medical emergency.',
                        'Family bereavement.',
                        'Transport disruption.',
                        'Hospitalisation.',
                        'Government appointment.',
                        'Severe illness.',
                    ]),
                    'attachment_path' => '/excuse-attachments/'.fake()->uuid().'.pdf',
                    'status' => $status,
                    'reviewed_by' => $isReviewed ? $staffIds[array_rand($staffIds)] : null,
                    'reviewed_at' => $isReviewed ? $engEnd->copy()->addDays(rand(1, 7)) : null,
                    'created_at' => $engEnd->copy()->addHours(rand(1, 24)),
                    'updated_at' => $engEnd->copy()->addHours(rand(1, 24)),
                ];
            }
        }

        foreach (array_chunk($attendanceRows, 1000) as $chunk) {
            DB::table('attendance_records')->insert($chunk);
        }
        foreach (array_chunk($excuseRows, 500) as $chunk) {
            DB::table('excuse_requests')->insert($chunk);
        }

        // -----------------------------------------------------------------------
        // 15. Attendance balance — computed for ALL cohorts from actual records
        //     Active cohort (2026) has future engagements so balance won't be 0,
        //     but past sessions within it are already accounted for.
        //     The service also computes this dynamically, so this is just for
        //     the stored column to reflect reality.
        // -----------------------------------------------------------------------
        $this->command->info('Computing attendance balances...');

        $allAttended = DB::table('attendance_records')
            ->whereNotNull('arrived_at')
            ->whereIn('student_id', $students->pluck('id')->toArray())
            ->get(['student_id', 'engagement_id'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('engagement_id')->toArray());

        $allExcuses = DB::table('excuse_requests')
            ->whereIn('student_id', $students->pluck('id')->toArray())
            ->get(['student_id', 'engagement_id', 'status'])
            ->groupBy('student_id');

        foreach ($students as $student) {
            // Only count past engagements for balance
            $expectedIds = [];
            foreach ($pastEngagements as $eng) {
                $ids = $this->resolveExpectedStudentIds($eng, $studentsByCohort, $studentsByLabGroup, $bsCohortMap);
                if (in_array($student->id, $ids)) {
                    $expectedIds[] = $eng->id;
                }
            }

            if (empty($expectedIds)) {
                continue;
            }

            $attendedIds = $allAttended->get($student->id, []);
            $absentIds = array_diff($expectedIds, $attendedIds);
            $studentExcuses = $allExcuses->get($student->id, collect());
            $approvedCount = $studentExcuses
                ->whereIn('engagement_id', $absentIds)
                ->where('status', ExcuseRequest::STATUS_APPROVED)
                ->count();

            $unexcusedCount = count($absentIds) - $approvedCount;
            $balance = max(0, 250 - ($unexcusedCount * 25) - ($approvedCount * 5));

            DB::table('student_profiles')->where('id', $student->id)->update(['attendance_balance' => $balance]);
        }

        // -----------------------------------------------------------------------
        // 16. Announcements
        // -----------------------------------------------------------------------
        $this->command->info('Creating announcements...');
        Announcement::factory(5)->global()->create(['staff_id' => $allStaff->random()->id]);
        foreach ($activeCohorts as $cohort) {
            Announcement::factory(2)->forCohort($cohort->id)->create(['staff_id' => $allStaff->random()->id]);
        }

        // -----------------------------------------------------------------------
        // 17. Billing records
        // -----------------------------------------------------------------------
        $this->command->info('Creating billing records...');
        $billingRows = [];
        foreach ($engagements->whereNotNull('absences_processed_at') as $engagement) {
            $billingRows[] = [
                'engagement_id' => $engagement->id,
                'staff_id' => $engagement->staff_id,
                'delivered_hours' => $engagement->scheduled_hours,
                'total_amount' => $engagement->scheduled_hours * 150,
                'forwarded_at' => rand(0, 1) ? $engagement->ends_at?->copy()->addDays(rand(3, 14)) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($billingRows, 500) as $chunk) {
            DB::table('billing_records')->insert($chunk);
        }

        DB::statement('SET session_replication_role = DEFAULT;');

        // -----------------------------------------------------------------------
        // 18. Test credentials
        // -----------------------------------------------------------------------
        $this->outputCredentials($branchManagers, $trackAdmins, $instructors, $students, $labGroups, $engagements, $cohorts);
        $this->command->info('Seeding complete!');
    }

    private function findFreeSlot(array &$slotUsage, mixed $ownerKey, string $date): ?int
    {
        for ($slot = 0; $slot < 3; $slot++) {
            if (! isset($slotUsage["{$ownerKey}:{$date}:{$slot}"])) {
                return $slot;
            }
        }

        return null;
    }

    private function resolveExpectedStudentIds(
        Engagement $engagement,
        Collection $studentsByCohort,
        Collection $studentsByLabGroup,
        Collection $bsCohortMap,
    ): array {
        $engageable = $engagement->engageable;
        if (! $engageable) {
            return [];
        }

        return match ($engagement->engageable_type) {
            Engagement::TYPE_COURSE => $studentsByCohort->get($engageable->cohort_id, []),
            Engagement::TYPE_LAB => $studentsByLabGroup->get($engageable->lab_group_id, []),
            Engagement::TYPE_BUSINESS_SESSION => collect($bsCohortMap->get($engageable->id, []))
                ->flatMap(fn ($cid) => $studentsByCohort->get($cid, []))
                ->unique()->values()->toArray(),
            default => [],
        };
    }

    private function outputCredentials(
        Collection $branchManagers,
        Collection $trackAdmins,
        Collection $instructors,
        Collection $students,
        Collection $labGroups,
        Collection $engagements,
        Collection $cohorts,
    ): void {
        echo "\n".str_repeat('=', 70)."\n";
        echo "GENERATED TEST USERS, TOKENS & ACCESS MAP\n";
        echo str_repeat('=', 70)."\n\n";

        $bmStaff = $branchManagers->first();
        $bmUser = $bmStaff->user;
        $bmUser->update(['email' => 'branch@iti.gov.eg', 'name' => 'Branch Manager (Test)']);
        $bmToken = $bmUser->createToken('test-token')->plainTextToken;
        echo "ROLE: branch_manager\n";
        echo "EMAIL: branch@iti.gov.eg | PASSWORD: password\n";
        echo "TOKEN: {$bmToken}\n";
        echo "USER ID: {$bmUser->id} | STAFF PROFILE ID: {$bmStaff->id}\n";
        echo str_repeat('-', 70)."\n";

        $taStaff = $trackAdmins->first();
        $taUser = $taStaff->user;
        $taUser->update(['email' => 'trackadmin@iti.gov.eg', 'name' => 'Track Admin (Test)']);
        $taToken = $taUser->createToken('test-token')->plainTextToken;
        $taCohortIds = DB::table('cohorts_admins')->where('staff_id', $taStaff->id)->pluck('cohort_id');
        echo "ROLE: track_admin\n";
        echo "EMAIL: trackadmin@iti.gov.eg | PASSWORD: password\n";
        echo "TOKEN: {$taToken}\n";
        echo "USER ID: {$taUser->id} | STAFF PROFILE ID: {$taStaff->id}\n";
        echo 'ASSIGNED COHORT IDS: '.$taCohortIds->implode(', ')."\n";
        echo str_repeat('-', 70)."\n";

        $instrStaff = $instructors->first();
        $instrUser = $instrStaff->user;
        $instrUser->update(['email' => 'instructor@iti.gov.eg', 'name' => 'Instructor (Test)']);
        $instrToken = $instrUser->createToken('test-token')->plainTextToken;
        echo "ROLE: instructor\n";
        echo "EMAIL: instructor@iti.gov.eg | PASSWORD: password\n";
        echo "TOKEN: {$instrToken}\n";
        echo "USER ID: {$instrUser->id} | STAFF PROFILE ID: {$instrStaff->id}\n";
        echo str_repeat('-', 70)."\n";

        $activeCohortId = $cohorts->where('is_active', true)->first()->id;
        $realStudent = $students->where('cohort_id', $activeCohortId)->first();
        $realStudentUser = $realStudent->user;
        $realStudentUser->update(['email' => 'student@iti.gov.eg', 'name' => 'Student (Test)']);
        $stToken = $realStudentUser->createToken('test-token')->plainTextToken;
        echo "ROLE: student\n";
        echo "EMAIL: student@iti.gov.eg | PASSWORD: password\n";
        echo "TOKEN: {$stToken}\n";
        echo "USER ID: {$realStudentUser->id} | STUDENT PROFILE ID: {$realStudent->id}\n";
        echo "COHORT ID: {$realStudent->cohort_id} | LAB GROUP ID: {$realStudent->lab_group_id}\n";
        echo str_repeat('-', 70)."\n";

        echo "\nAll passwords: password\n";
        echo str_repeat('=', 70)."\n\n";
    }
}
