<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Engagement;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingRollupTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test authorization for Billing Rollup
     */
    public function test_branch_manager_can_access_billing_rollup(): void
    {
        $manager = User::where('role', 'branch_manager')->first()
            ?? User::factory()->branchManager()->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/billing/rollup');

        $response->assertStatus(200);
    }

    public function test_track_admin_cannot_access_billing_rollup(): void
    {
        $admin = User::factory()->trackAdmin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/billing/rollup');

        $response->assertStatus(403);
    }

    public function test_student_cannot_access_billing_rollup(): void
    {
        $student = User::factory()->student()->create();

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/billing/rollup');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_billing_rollup(): void
    {
        $response = $this->getJson('/api/billing/rollup');

        $response->assertStatus(401);
    }

    /**
     * Test structure and calculations
     */
    public function test_billing_rollup_returns_correct_structure(): void
    {
        $manager = User::where('role', 'branch_manager')->first()
            ?? User::factory()->branchManager()->create();

        $staffUser = User::factory()->instructor()->create();
        $staffProfile = StaffProfile::factory()->create([
            'user_id' => $staffUser->id,
            'compensation_type' => 'external',
            'hourly_rate' => 100,
            'fixed_salary' => 0,
        ]);
        $course = Course::factory()->create();
        Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 5,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->subHours(8),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/billing/rollup');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'total_cost',
                    'internal_cost',
                    'external_cost',
                ],
                'by_staff' => [
                    'data' => [
                        '*' => [
                            'staff_profile_id',
                            'name',
                            'compensation_type',
                            'scheduled_hours',
                            'hourly_rate',
                            'billing_amount',
                        ],
                    ],
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    /**
     * Test dynamic calculations and only including finished engagements
     */
    public function test_billing_rollup_includes_only_finished_engagements(): void
    {
        // 1. Create a branch manager
        $manager = User::factory()->branchManager()->create();

        // 2. Create staff profile
        $staffUser = User::factory()->instructor()->create(['name' => 'Test Instructor']);
        $staffProfile = StaffProfile::factory()->create([
            'user_id' => $staffUser->id,
            'compensation_type' => 'external',
            'hourly_rate' => 100,
            'fixed_salary' => 0,
        ]);

        // 3. Create a course to use as the polymorphic engageable target
        $course = Course::factory()->create();

        // 4. Create a finished engagement (ends_at <= now)
        Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 5,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->subHours(8),
        ]);

        // 5. Create an ongoing/future engagement (ends_at > now)
        Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 10,
            'starts_at' => now()->addHours(1),
            'ends_at' => now()->addHours(3),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/billing/rollup');

        $response->assertStatus(200);

        // Find this staff member in by_staff array
        $byStaff = $response->json('by_staff.data');
        $this->assertNotEmpty($byStaff);

        $targetStaffRecord = collect($byStaff)->firstWhere('staff_profile_id', $staffProfile->id);
        $this->assertNotNull($targetStaffRecord);

        // It should ONLY sum scheduled hours of finished engagements (5, not 15)
        $this->assertEquals(5.0, $targetStaffRecord['scheduled_hours']);
        $this->assertEquals(500.0, $targetStaffRecord['billing_amount']);
    }

    public function test_billing_rollup_excludes_staff_without_finished_engagements(): void
    {
        $manager = User::factory()->branchManager()->create();

        // 1. Create a staff member with no engagements
        $staffUser = User::factory()->instructor()->create();
        $staffProfile = StaffProfile::factory()->create([
            'user_id' => $staffUser->id,
        ]);

        // 2. Create another staff member with a finished engagement
        $staffUserWithEngagement = User::factory()->instructor()->create();
        $staffProfileWithEngagement = StaffProfile::factory()->create([
            'user_id' => $staffUserWithEngagement->id,
        ]);
        $course = Course::factory()->create();
        Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfileWithEngagement->id,
            'ends_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/billing/rollup');

        $response->assertStatus(200);

        $byStaffIds = collect($response->json('by_staff.data'))->pluck('staff_profile_id');

        $this->assertNotContains($staffProfile->id, $byStaffIds);
        $this->assertContains($staffProfileWithEngagement->id, $byStaffIds);
    }
}
