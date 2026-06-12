<?php

namespace Tests\Feature;

use App\Enums\CompensationType;
use App\Models\Announcement;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AnnouncementTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Branch Manager can view any cohort's announcements.
     */
    public function test_branch_manager_can_view_announcements(): void
    {
        $manager = User::factory()->branchManager()->create();
        $cohort = Cohort::first();
        $this->assertNotNull($cohort, 'No cohort found to test');

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/cohorts/{$cohort->id}/announcements");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'status',
            'data',
        ]);
    }

    /**
     * Branch Manager can post an announcement to any cohort.
     */
    public function test_branch_manager_can_store_announcement(): void
    {
        $manager = User::factory()->branchManager()->create();
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $response = $this->actingAs($manager, 'sanctum')
            ->postJson("/api/cohorts/{$cohort->id}/announcements", [
                'title' => 'Test Announcement',
                'body' => 'This is a test announcement body with enough length.',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.cohort_id', $cohort->id);
    }

    /**
     * A Track Admin assigned to a cohort can post to it.
     */
    public function test_authorized_track_admin_can_store_announcement(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $adminStaff = $cohort->trackAdmins()->first();
        if (! $adminStaff) {
            $adminUser = User::factory()->trackAdmin()->create();
            $adminStaff = StaffProfile::factory()->create(['user_id' => $adminUser->id]);
            $cohort->trackAdmins()->attach($adminStaff->id);
        } else {
            $adminUser = $adminStaff->user;
        }

        $response = $this->actingAs($adminUser, 'sanctum')
            ->postJson("/api/cohorts/{$cohort->id}/announcements", [
                'title' => 'TA Announcement',
                'body' => 'This is a test announcement body with enough length.',
            ]);

        $response->assertStatus(201);
    }

    /**
     * A Track Admin NOT assigned to a cohort cannot post to it.
     */
    public function test_unauthorized_track_admin_cannot_store_announcement(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $unauthorizedAdmin = User::factory()->trackAdmin()->create();
        StaffProfile::factory()->create(['user_id' => $unauthorizedAdmin->id]);

        $response = $this->actingAs($unauthorizedAdmin, 'sanctum')
            ->postJson("/api/cohorts/{$cohort->id}/announcements", [
                'title' => 'Should Fail',
                'body' => 'This is a test announcement body with enough length.',
            ]);

        $response->assertStatus(403);
    }

    /**
     * An instructor with an active engagement can post to that cohort.
     */
    public function test_instructor_with_active_engagement_can_store_announcement(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $instructorUser = User::factory()->instructor()->create();
        $instructorStaff = StaffProfile::factory()->create([
            'user_id' => $instructorUser->id,
            'compensation_type' => CompensationType::INTERNAL,
        ]);

        // Find a course in this cohort to engage against
        $course = $cohort->courses()->first();
        $this->assertNotNull($course, 'Cohort needs at least one course for this test');

        Engagement::factory()->create([
            'staff_id' => $instructorStaff->id,
            'engageable_id' => $course->id,
            'engageable_type' => Course::class,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($instructorUser, 'sanctum')
            ->postJson("/api/cohorts/{$cohort->id}/announcements", [
                'title' => 'Instructor Announcement',
                'body' => 'This is a test announcement body with enough length.',
            ]);

        $response->assertStatus(201);
    }

    /**
     * An instructor with NO active engagement cannot post.
     */
    public function test_instructor_without_active_engagement_cannot_store_announcement(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $instructorUser = User::factory()->instructor()->create();
        StaffProfile::factory()->create([
            'user_id' => $instructorUser->id,
            'compensation_type' => CompensationType::INTERNAL,
        ]);

        $response = $this->actingAs($instructorUser, 'sanctum')
            ->postJson("/api/cohorts/{$cohort->id}/announcements", [
                'title' => 'Should Fail',
                'body' => 'This is a test announcement body with enough length.',
            ]);

        $response->assertStatus(403);
    }

    /**
     * A student in the cohort can view announcements.
     */
    public function test_student_in_cohort_can_view_announcements(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $studentProfile = StudentProfile::where('cohort_id', $cohort->id)->first();
        if (! $studentProfile) {
            $studentUser = User::factory()->student()->create();
            $studentProfile = StudentProfile::factory()->create([
                'user_id' => $studentUser->id,
                'cohort_id' => $cohort->id,
                'attendance_balance' => 250,
            ]);
        } else {
            $studentUser = $studentProfile->user;
        }

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson("/api/cohorts/{$cohort->id}/announcements");

        $response->assertStatus(200);
    }

    /**
     * A student NOT in the cohort cannot view its announcements.
     */
    public function test_student_outside_cohort_cannot_view_announcements(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        // Create a student in a different cohort (or none)
        $otherCohort = Cohort::where('id', '!=', $cohort->id)->first();

        $studentUser = User::factory()->student()->create();
        StudentProfile::factory()->create([
            'user_id' => $studentUser->id,
            'cohort_id' => $otherCohort?->id,
            'attendance_balance' => 250,
        ]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson("/api/cohorts/{$cohort->id}/announcements");

        $response->assertStatus(403);
    }

    /**
     * A student cannot post announcements.
     */
    public function test_student_cannot_store_announcement(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $studentProfile = StudentProfile::where('cohort_id', $cohort->id)->first();
        $studentUser = $studentProfile ? $studentProfile->user : User::factory()->student()->create();

        $response = $this->actingAs($studentUser, 'sanctum')
            ->postJson("/api/cohorts/{$cohort->id}/announcements", [
                'title' => 'Should Fail',
                'body' => 'This is a test announcement body with enough length.',
            ]);

        $response->assertStatus(403);
    }

    /**
     * The author (instructor) can update their own announcement during their window.
     */
    public function test_instructor_can_update_own_announcement_during_window(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $instructorUser = User::factory()->instructor()->create();
        $instructorStaff = StaffProfile::factory()->create([
            'user_id' => $instructorUser->id,
            'compensation_type' => CompensationType::INTERNAL,
        ]);

        $course = $cohort->courses()->first();
        $this->assertNotNull($course);

        Engagement::factory()->create([
            'staff_id' => $instructorStaff->id,
            'engageable_id' => $course->id,
            'engageable_type' => Course::class,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
        ]);

        $announcement = Announcement::factory()->create([
            'cohort_id' => $cohort->id,
            'staff_id' => $instructorStaff->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($instructorUser, 'sanctum')
            ->patchJson("/api/announcements/{$announcement->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.title', 'Updated Title');
    }

    /**
     * An instructor cannot update another instructor's announcement.
     */
    public function test_instructor_cannot_update_others_announcement(): void
    {
        $cohort = Cohort::first();
        $this->assertNotNull($cohort);

        $authorUser = User::factory()->instructor()->create();
        $authorStaff = StaffProfile::factory()->create([
            'user_id' => $authorUser->id,
            'compensation_type' => CompensationType::INTERNAL,
        ]);

        $announcement = Announcement::factory()->create([
            'cohort_id' => $cohort->id,
            'staff_id' => $authorStaff->id,
            'published_at' => now(),
        ]);

        $otherInstructorUser = User::factory()->instructor()->create();
        StaffProfile::factory()->create([
            'user_id' => $otherInstructorUser->id,
            'compensation_type' => CompensationType::INTERNAL,
        ]);

        $response = $this->actingAs($otherInstructorUser, 'sanctum')
            ->patchJson("/api/announcements/{$announcement->id}", [
                'title' => 'Hijacked Title',
            ]);

        $response->assertStatus(403);
    }

    /**
     * Cannot change cohort_id on update — prohibited field.
     */
    public function test_cannot_change_cohort_id_on_update(): void
    {
        $manager = User::factory()->branchManager()->create();
        $cohort = Cohort::first();
        $otherCohort = Cohort::where('id', '!=', $cohort->id)->first();
        $this->assertNotNull($otherCohort, 'Need at least 2 cohorts for this test');

        $announcement = Announcement::factory()->create([
            'cohort_id' => $cohort->id,
            'staff_id' => $manager->staffProfile->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->patchJson("/api/announcements/{$announcement->id}", [
                'cohort_id' => $otherCohort->id,
            ]);

        $response->assertStatus(422);
    }

    /**
     * Branch Manager can delete any announcement.
     */
    public function test_branch_manager_can_delete_announcement(): void
    {
        $manager = User::factory()->branchManager()->create();
        $cohort = Cohort::first();

        $announcement = Announcement::factory()->create([
            'cohort_id' => $cohort->id,
            'staff_id' => $manager->staffProfile->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->deleteJson("/api/announcements/{$announcement->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('announcements', ['id' => $announcement->id]);
    }

    /**
     * Instructor cannot delete an announcement (no delete route for them).
     */
    public function test_instructor_cannot_delete_announcement(): void
    {
        $cohort = Cohort::first();

        $instructorUser = User::factory()->instructor()->create();
        $instructorStaff = StaffProfile::factory()->create([
            'user_id' => $instructorUser->id,
            'compensation_type' => CompensationType::INTERNAL,
        ]);

        $announcement = Announcement::factory()->create([
            'cohort_id' => $cohort->id,
            'staff_id' => $instructorStaff->id,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($instructorUser, 'sanctum')
            ->deleteJson("/api/announcements/{$announcement->id}");

        $response->assertStatus(403);
    }
}
