<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Engagement;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InstructorBillingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_branch_manager_can_access_instructor_billing(): void
    {
        $manager = User::factory()->branchManager()->create();
        $staffUser = User::factory()->instructor()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staffUser->id]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/billing/instructors/{$staffProfile->id}");

        $response->assertStatus(200);
    }

    public function test_track_admin_cannot_access_instructor_billing(): void
    {
        $admin = User::factory()->trackAdmin()->create();
        $staffUser = User::factory()->instructor()->create();
        $staffProfile = StaffProfile::factory()->create(['user_id' => $staffUser->id]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/billing/instructors/{$staffProfile->id}");

        $response->assertStatus(403);
    }

    public function test_instructor_billing_returns_404_if_not_found(): void
    {
        $manager = User::factory()->branchManager()->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/billing/instructors/99999');

        $response->assertStatus(404);
    }

    public function test_instructor_billing_returns_correct_structure(): void
    {
        $manager = User::factory()->branchManager()->create();
        $staffUser = User::factory()->instructor()->create();
        $staffProfile = StaffProfile::factory()->create([
            'user_id' => $staffUser->id,
            'compensation_type' => 'internal',
            'fixed_salary' => 3000,
            'hourly_rate' => 150,
        ]);

        $course = Course::factory()->create();

        // 1. Finished engagement
        Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 4,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(4),
        ]);

        // 2. In progress / future engagement
        Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 3,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/billing/instructors/{$staffProfile->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'staff_profile_id',
                'name',
                'compensation_type',
                'fixed_salary',
                'hourly_rate',
                'payout_summary' => [
                    'total_delivered_hours',
                    'base_salary_component',
                    'hourly_component',
                    'total_calculated_payout',
                ],
                'engagements' => [
                    'data' => [
                        '*' => [
                            'engagement_id',
                            'type',
                            'title',
                            'starts_at',
                            'ends_at',
                            'scheduled_hours',
                            'estimated_payout',
                            'status',
                        ],
                    ],
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_instructor_billing_accurately_calculates_status_and_totals(): void
    {
        $manager = User::factory()->branchManager()->create();
        $staffUser = User::factory()->instructor()->create();

        // Let's use internal compensation to test base salary component inclusion
        $staffProfile = StaffProfile::factory()->create([
            'user_id' => $staffUser->id,
            'compensation_type' => 'internal',
            'fixed_salary' => 5000,
            'hourly_rate' => 200,
        ]);

        $course = Course::factory()->create();

        // 1. Finished and NOT forwarded (no billing record) -> status "pending"
        $engPending = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 5,
            'starts_at' => now()->subDays(5),
            'ends_at' => now()->subDays(5)->addHours(5),
        ]);

        // 2. Finished and forwarded -> status "forwarded"
        $engForwarded = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 8,
            'starts_at' => now()->subDays(4),
            'ends_at' => now()->subDays(4)->addHours(8),
        ]);

        DB::table('billing_records')->insert([
            'engagement_id' => $engForwarded->id,
            'staff_id' => $staffProfile->id,
            'delivered_hours' => 8,
            'total_amount' => 1600,
            'forwarded_at' => now()->subDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Ongoing / Future engagement -> status "in_progress"
        $engInProgress = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $staffProfile->id,
            'scheduled_hours' => 10,
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(4),
        ]);

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson("/api/billing/instructors/{$staffProfile->id}");

        $response->assertStatus(200);

        // Verify Payout Summary calculations
        // total_delivered_hours should sum only finished engagements: 5 + 8 = 13 (ignores 10 since it's future)
        $summary = $response->json('payout_summary');
        $this->assertEquals(13, $summary['total_delivered_hours']);
        $this->assertEquals(5000, $summary['base_salary_component']);
        $this->assertEquals(2600, $summary['hourly_component']);        // 13 * 200
        $this->assertEquals(7600, $summary['total_calculated_payout']); // 5000 + 2600

        // Verify individual engagements details
        $engagementsList = collect($response->json('engagements.data'));

        $recPending = $engagementsList->firstWhere('engagement_id', $engPending->id);
        $this->assertEquals('pending', $recPending['status']);
        $this->assertEquals(5 * 200.0, $recPending['estimated_payout']);

        $recForwarded = $engagementsList->firstWhere('engagement_id', $engForwarded->id);
        $this->assertEquals('forwarded', $recForwarded['status']);
        $this->assertEquals(8 * 200.0, $recForwarded['estimated_payout']);

        $recInProgress = $engagementsList->firstWhere('engagement_id', $engInProgress->id);
        $this->assertEquals('in_progress', $recInProgress['status']);
        $this->assertEquals(10 * 200.0, $recInProgress['estimated_payout']);
    }
}
