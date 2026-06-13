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

        // Create specific "Special" Staff for custom echo requirement
        $specialTaUser = User::factory()->trackAdmin()->create([
            'name' => 'Special Track Admin',
            'email' => 'trackadmin_special@iti.gov.eg',
        ]);
        $specialTa = StaffProfile::factory()->create(['user_id' => $specialTaUser->id]);

        $specialInstrUser = User::factory()->instructor()->create([
            'name' => 'Special Lab Instructor',
            'email' => 'instructor_special@iti.gov.eg',
        ]);
        $specialInstr = StaffProfile::factory()->create(['user_id' => $specialInstrUser->id]);

        // General track admins & instructors
        $trackAdmins = collect([$specialTa]);
        for ($i = 0; $i < 9; $i++) {
            $user = User::factory()->trackAdmin()->create();
            $trackAdmins->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $instructors = collect([$specialInstr]);
        for ($i = 0; $i < 19; $i++) {
            $user = User::factory()->instructor()->create();
            $instructors->push(StaffProfile::factory()->create(['user_id' => $user->id]));
        }

        $allStaff = $branchManagers->concat($trackAdmins)->concat($instructors);
        $staffIds = $allStaff->pluck('id')->toArray();
        $instrIds = $instructors->pluck('id')->toArray();
        $taIds = $trackAdmins->pluck('id')->toArray();

        // -----------------------------------------------------------------------
        // 3. Tracks (exactly 6 to 8 - we will make 6)
        // -----------------------------------------------------------------------
        $this->command->info('Creating 6 tracks...');
        $trackNames = [
            'Full-Stack Web Development',
            'Mobile Application Development',
            'Cloud Computing & DevOps',
            'Cyber Security & Ethical Hacking',
            'Data Science & Artificial Intelligence',
            'UI/UX Product Design', // We will make this the special track
        ];

        $tracks = collect();
        foreach ($trackNames as $name) {
            $tracks->push(Track::factory()->create(['name' => $name]));
        }
        $specialTrack = $tracks->where('name', 'UI/UX Product Design')->first();

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

        // -----------------------------------------------------------------------
        // 5. Assign track admins to active cohorts
        // -----------------------------------------------------------------------
        $this->command->info('Assigning track admins...');
        $taCollection = $trackAdmins->values();
        $taCount = $taCollection->count();
        $insertRows = [];

        foreach ($activeCohorts as $idx => $cohort) {
            // Ensure special Track Admin manages the special track's active cohort
            if ($cohort->track_id === $specialTrack->id) {
                $admin1 = $specialTa;
                $admin2 = $taCollection[($idx + 1) % $taCount];
            } else {
                $admin1 = $taCollection[$idx % $taCount];
                if ($admin1->id === $specialTa->id) {
                    $admin1 = $taCollection[($idx + 2) % $taCount];
                }
                $admin2 = $taCollection[($idx + 1) % $taCount];
                if ($admin2->id === $specialTa->id) {
                    $admin2 = $taCollection[($idx + 3) % $taCount];
                }
            }

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
        // 7. Students (different student counts per cohort/track from 20 to 30)
        // -----------------------------------------------------------------------
        $this->command->info('Creating students with varied counts and profiles...');
        $students = collect();
        
        // Create the special student in UI/UX Product Design active cohort
        $specialCohort = $activeCohorts->where('track_id', $specialTrack->id)->first();
        $specialCohortLabGroups = $labGroups->where('cohort_id', $specialCohort->id)->values();
        $specialLg = $specialCohortLabGroups->first(); // Group Alpha

        $specialStudentUser = User::factory()->student()->create([
            'name' => 'Special Student (UI/UX)',
            'email' => 'student_special@iti.gov.eg',
        ]);
        $specialStudent = StudentProfile::factory()->create([
            'user_id' => $specialStudentUser->id,
            'cohort_id' => $specialCohort->id,
            'lab_group_id' => $specialLg->id,
            'attendance_balance' => 250,
        ]);
        $students->push($specialStudent);

        foreach ($cohorts as $cohort) {
            $cohortLabGroups = $labGroups->where('cohort_id', $cohort->id)->values();
            $lgCount = $cohortLabGroups->count();

            // Determine randomized student count per cohort from 20 to 30
            $targetCount = rand(20, 30);
            
            // Adjust if we already added the special student to this cohort
            $startIdx = ($cohort->id === $specialCohort->id) ? 1 : 0;

            for ($i = $startIdx; $i < $targetCount; $i++) {
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
        // 11. Deliverables (Ensure weights sum exactly to 100 per course)
        // -----------------------------------------------------------------------
        $this->command->info('Creating deliverables...');
        $deliverables = collect();
        $weights = [40, 30, 30]; // Must sum to 100
        
        foreach ($courses as $course) {
            for ($i = 0; $i < self::DELIVERABLES_PER_COURSE; $i++) {
                $deliverables->push(
                    CourseDeliverable::factory()->create([
                        'course_id' => $course->id,
                        'course_weight' => $weights[$i],
                        'max_score' => 100, // Make max score consistent
                    ])
                );
            }
        }

        // -----------------------------------------------------------------------
        // 12. Engagements (with different start dates and status combinations)
        // -----------------------------------------------------------------------
        $this->command->info('Creating engagements...');
        $slotUsage = [];
        $engagementRows = [];

        $assignStaff = function (string $type, $cohort, $labGroup = null) use ($instrIds, $taIds, $specialInstr, $specialLg) {
            // If this is the special UI/UX student's lab group, assign the special instructor
            if ($type === 'lab' && $labGroup && $labGroup->id === $specialLg->id) {
                return $specialInstr->id;
            }
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

            // Generate weekdays
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
                // Varying starts_at dates across the year
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
                        'staff_id' => $assignStaff('course', $cohort),
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
                            'staff_id' => $assignStaff('lab', $cohort, $labGroup),
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
        // 13. Submissions (Variety: Some pass, some fail based on student profile index)
        // -----------------------------------------------------------------------
        $this->command->info('Creating submissions...');
        $studentIdsByCohort = $students->groupBy('cohort_id');
        $submissionRows = [];

        foreach ($deliverables as $deliverable) {
            $cohortStudents = $studentIdsByCohort->get($deliverable->course->cohort_id ?? 0, collect());
            if ($cohortStudents->isEmpty()) {
                continue;
            }

            // All students should have deliverables to show correct grade aggregates
            foreach ($cohortStudents as $student) {
                $isGraded = rand(0, 100) < 90; // Most are graded
                $isOverridden = $isGraded && rand(0, 100) < 8;

                // Determine student grade group based on ID to create clean cohorts:
                // Student profile ID % 4 determines passing tier:
                // - ID % 4 === 0 -> Failing student (Grades 25% - 55%)
                // - ID % 4 === 1 -> Average student (Grades 60% - 78%)
                // - ID % 4 === 2 -> Excellent student (Grades 82% - 98%)
                // - ID % 4 === 3 -> Mixed (some courses failed, some passed)
                $mod = $student->id % 4;
                if ($mod === 0) {
                    $scorePct = rand(25, 55) / 100.0;
                } elseif ($mod === 1) {
                    $scorePct = rand(60, 78) / 100.0;
                } elseif ($mod === 2) {
                    $scorePct = rand(82, 98) / 100.0;
                } else {
                    $scorePct = (rand(0, 1) === 0) ? rand(35, 55) / 100.0 : rand(65, 88) / 100.0;
                }

                $rawScore = round($deliverable->max_score * $scorePct, 1);
                $overrideScore = $isOverridden ? round($deliverable->max_score * min(1.0, $scorePct + 0.1), 1) : null;

                $submissionRows[] = [
                    'deliverable_id' => $deliverable->id,
                    'student_id' => $student->id,
                    'submission_type' => ($student->id % 2 === 0) ? 'file' : 'link',
                    'submission_path' => 'https://github.com/student/project-' . $student->id . '-' . $deliverable->id,
                    'raw_score' => $rawScore,
                    'graded_by' => $isGraded ? $staffIds[array_rand($staffIds)] : null,
                    'override_score' => $overrideScore,
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
        // 14. Attendance records + excuse requests (Variety: absent patterns)
        // -----------------------------------------------------------------------
        $this->command->info('Creating attendance records with varied absences...');

        $pastEngagements = $engagements->filter(fn ($e) => $e->ends_at?->isPast());
        $studentsByCohort = $students->groupBy('cohort_id')->map(fn ($g) => $g->pluck('id')->toArray());
        $studentsByLabGroup = $students->groupBy('lab_group_id')->map(fn ($g) => $g->pluck('id')->toArray());
        $bsCohortMap = DB::table('business_sessions_cohorts')->get()
            ->groupBy('business_session_id')
            ->map(fn ($rows) => $rows->pluck('cohort_id')->toArray());

        $attendanceRows = [];
        $excuseRows = [];

        $excuseReasons = [
            'Medical emergency.',
            'Family bereavement.',
            'Transport disruption.',
            'Hospitalisation.',
            'Government appointment.',
            'Severe illness.',
        ];

        foreach ($pastEngagements as $engagement) {
            $studentIds = $this->resolveExpectedStudentIds($engagement, $studentsByCohort, $studentsByLabGroup, $bsCohortMap);
            if (empty($studentIds)) {
                continue;
            }

            $engStart = $engagement->starts_at;
            $engEnd = $engagement->ends_at;

            foreach ($studentIds as $studentId) {
                // Determine attendance group based on ID to create clean balance differences:
                // - studentId % 5 === 0 -> Chronically absent / at-risk attendance (35% absence rate)
                // - studentId % 5 === 1 -> Occasional absence (15% absence rate)
                // - Others -> Perfect/near perfect (2% absence rate)
                $mod = $studentId % 5;
                $absenceThreshold = ($mod === 0) ? 35 : (($mod === 1) ? 15 : 2);
                
                $isAbsent = (rand(1, 100) <= $absenceThreshold);

                if (! $isAbsent) {
                    $attendanceRows[] = [
                        'engagement_id' => $engagement->id,
                        'student_id' => $studentId,
                        // Avoid copy() and carbon method chains to reduce memory overhead
                        'arrived_at' => $engStart,
                        'left_at' => $engEnd,
                        'created_at' => $engStart,
                        'updated_at' => $engStart,
                    ];
                } else {
                    // Must insert a null arrived_at record for the analytics denominator to be correct
                    $attendanceRows[] = [
                        'engagement_id' => $engagement->id,
                        'student_id' => $studentId,
                        'arrived_at' => null,
                        'left_at' => null,
                        'created_at' => $engStart,
                        'updated_at' => $engStart,
                    ];

                    // Excuses for absent students: Chronically absent students rarely submit approved excuses
                    if ($mod === 0) {
                        // Chronically absent: 85% remain unexcused
                        $excuseRoll = rand(1, 100);
                        if ($excuseRoll <= 15) {
                            $status = ExcuseRequest::STATUS_PENDING;
                        } elseif ($excuseRoll <= 35) {
                            $status = ExcuseRequest::STATUS_REJECTED;
                        } else {
                            continue;
                        }
                    } else {
                        // Regular students: 80% excuse rate, mostly approved
                        $excuseRoll = rand(1, 100);
                        if ($excuseRoll <= 70) {
                            $status = ExcuseRequest::STATUS_APPROVED;
                        } elseif ($excuseRoll <= 85) {
                            $status = ExcuseRequest::STATUS_PENDING;
                        } else {
                            $status = ExcuseRequest::STATUS_REJECTED;
                        }
                    }

                    $isReviewed = in_array($status, [ExcuseRequest::STATUS_APPROVED, ExcuseRequest::STATUS_REJECTED]);
                    $excuseRows[] = [
                        'engagement_id' => $engagement->id,
                        'student_id' => $studentId,
                        'reason' => $excuseReasons[rand(0, 5)],
                        'attachment_path' => '/excuse-attachments/excuse-' . $studentId . '-' . $engagement->id . '.pdf',
                        'status' => $status,
                        'reviewed_by' => $isReviewed ? $staffIds[array_rand($staffIds)] : null,
                        'reviewed_at' => $isReviewed ? $engEnd : null,
                        'created_at' => $engEnd,
                        'updated_at' => $engEnd,
                    ];
                }
            }
        }

        $this->command->info('Inserting attendance records...');
        foreach (array_chunk($attendanceRows, 1000) as $chunk) {
            DB::table('attendance_records')->insert($chunk);
        }

        $this->command->info('Inserting excuse requests...');
        foreach (array_chunk($excuseRows, 500) as $chunk) {
            DB::table('excuse_requests')->insert($chunk);
        }

        // -----------------------------------------------------------------------
        // 15. Attendance balance — computed from actual logs in past engagements
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
            // Find past engagements that expected this student
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
            
            // Unexcused = -25 points, Approved = -5 points
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
        // 17. Billing records (REMOVED: Leave for the batch job to populate)
        // -----------------------------------------------------------------------
        $this->command->info('Skipping billing_records insertion (will be populated by the billing job)...');

        DB::statement('SET session_replication_role = DEFAULT;');

        // -----------------------------------------------------------------------
        // 18. Output Credentials & Echoing Special Supervision Map
        // -----------------------------------------------------------------------
        $this->outputSpecialSupervision($specialTa, $specialStudent, $specialInstr, $specialTrack, $specialLg);
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

    private function outputSpecialSupervision(
        StaffProfile $specialTa,
        StudentProfile $specialStudent,
        StaffProfile $specialInstr,
        Track $specialTrack,
        LabGroup $specialLg
    ): void {
        echo "\n".str_repeat('=', 70)."\n";
        echo "SPECIAL SUPERVISION / MAPPING RELATION\n";
        echo str_repeat('=', 70)."\n";
        echo "TRACK: {$specialTrack->name} (ID: {$specialTrack->id})\n";
        echo "TRACK ADMIN: {$specialTa->user->name} | EMAIL: {$specialTa->user->email}\n";
        echo "STUDENT: {$specialStudent->user->name} | EMAIL: {$specialStudent->user->email} | LAB GROUP: {$specialLg->name}\n";
        echo "LAB SUPERVISOR INSTRUCTOR: {$specialInstr->user->name} | EMAIL: {$specialInstr->user->email}\n";
        echo str_repeat('=', 70)."\n\n";
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
        echo str_repeat('=', 70)."\n";
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
        $taToken = $taUser->createToken('test-token')->plainTextToken;
        $taCohortIds = DB::table('cohorts_admins')->where('staff_id', $taStaff->id)->pluck('cohort_id');
        echo "ROLE: track_admin\n";
        echo "EMAIL: {$taUser->email} | PASSWORD: password\n";
        echo "TOKEN: {$taToken}\n";
        echo "USER ID: {$taUser->id} | STAFF PROFILE ID: {$taStaff->id}\n";
        echo 'ASSIGNED COHORT IDS: '.$taCohortIds->implode(', ')."\n";
        echo str_repeat('-', 70)."\n";

        $instrStaff = $instructors->first();
        $instrUser = $instrStaff->user;
        $instrToken = $instrUser->createToken('test-token')->plainTextToken;
        echo "ROLE: instructor\n";
        echo "EMAIL: {$instrUser->email} | PASSWORD: password\n";
        echo "TOKEN: {$instrToken}\n";
        echo "USER ID: {$instrUser->id} | STAFF PROFILE ID: {$instrStaff->id}\n";
        echo str_repeat('-', 70)."\n";

        $activeCohortId = $cohorts->where('is_active', true)->first()->id;
        $realStudent = $students->where('cohort_id', $activeCohortId)->first();
        $realStudentUser = $realStudent->user;
        $stToken = $realStudentUser->createToken('test-token')->plainTextToken;
        echo "ROLE: student\n";
        echo "EMAIL: {$realStudentUser->email} | PASSWORD: password\n";
        echo "TOKEN: {$stToken}\n";
        echo "USER ID: {$realStudentUser->id} | STUDENT PROFILE ID: {$realStudent->id}\n";
        echo "COHORT ID: {$realStudent->cohort_id} | LAB GROUP ID: {$realStudent->lab_group_id}\n";
        echo str_repeat('-', 70)."\n";

        echo "\nAll passwords: password\n";
        echo str_repeat('=', 70)."\n\n";
    }
}
