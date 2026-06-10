<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AtRiskStudentsTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test that a branch manager can get the list of at-risk students.
     */
    public function test_branch_manager_can_view_at_risk_students(): void
    {
        // Find or create a branch manager
        $manager = User::where('role', 'branch_manager')->first();
        if (! $manager) {
            $manager = User::factory()->branchManager()->create();
        }

        // Find a cohort
        $cohort = Cohort::first();
        $this->assertNotNull($cohort, 'No cohort found to test');

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/analytics/cohorts/{$cohort->id}/at-risk");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'student_id',
                'name',
                'email',
                'attendance_balance',
                'at_risk_attendance',
                'at_risk_grade',
                'failing_courses' => [
                    '*' => [
                        'course_id',
                        'course_name',
                        'score',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Test that an authorized track admin can view at-risk students.
     */
    public function test_authorized_track_admin_can_view_at_risk_students(): void
    {
        // Find a cohort
        $cohort = Cohort::first();
        $this->assertNotNull($cohort, 'No cohort found to test');

        // Get or create an admin for this cohort
        $adminStaff = $cohort->trackAdmins()->first();
        if (! $adminStaff) {
            $adminUser = User::factory()->trackAdmin()->create();
            $adminStaff = StaffProfile::factory()->create(['user_id' => $adminUser->id]);
            $cohort->trackAdmins()->attach($adminStaff->id);
        } else {
            $adminUser = $adminStaff->user;
        }

        $response = $this->actingAs($adminUser, 'sanctum')
            ->getJson("/api/analytics/cohorts/{$cohort->id}/at-risk");

        $response->assertStatus(200);
    }

    /**
     * Test that an unauthorized track admin cannot view at-risk students.
     */
    public function test_unauthorized_track_admin_cannot_view_at_risk_students(): void
    {
        // Find a cohort
        $cohort = Cohort::first();
        $this->assertNotNull($cohort, 'No cohort found to test');

        // Create a track admin who is NOT assigned to this cohort
        $unauthorizedAdminUser = User::factory()->trackAdmin()->create();
        StaffProfile::factory()->create(['user_id' => $unauthorizedAdminUser->id]);

        // Ensure they are not attached to cohorts_admins
        // (since it's a new user, they won't be attached)

        $response = $this->actingAs($unauthorizedAdminUser, 'sanctum')
            ->getJson("/api/analytics/cohorts/{$cohort->id}/at-risk");

        $response->assertStatus(403);
    }

    /**
     * Test that students cannot view at-risk students.
     */
    public function test_students_cannot_view_at_risk_students(): void
    {
        $student = User::where('role', 'student')->first();
        if (! $student) {
            $student = User::factory()->student()->create();
        }

        $cohort = Cohort::first();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson("/api/analytics/cohorts/{$cohort->id}/at-risk");

        $response->assertStatus(403);
    }
}
