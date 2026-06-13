<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Engagement;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProcessDailyBillingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_billing_processing_command_creates_billing_records_accurately(): void
    {
        // 1. Create a course and staff members
        $course = Course::factory()->create();

        // Internal staff: fixed salary + hourly
        $internalUser = User::factory()->instructor()->create();
        $internalStaff = StaffProfile::factory()->create([
            'user_id' => $internalUser->id,
            'compensation_type' => 'internal',
            'fixed_salary' => 4000,
            'hourly_rate' => 150,
        ]);

        // External staff: hourly only
        $externalUser = User::factory()->instructor()->create();
        $externalStaff = StaffProfile::factory()->create([
            'user_id' => $externalUser->id,
            'compensation_type' => 'external',
            'fixed_salary' => 0,
            'hourly_rate' => 100,
        ]);

        // 2. Setup engagements
        // Finished internal engagement 1 (scheduled hours: 4)
        $engInternal1 = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $internalStaff->id,
            'scheduled_hours' => 4,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(4),
        ]);

        // Finished internal engagement 2 (scheduled hours: 6)
        $engInternal2 = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $internalStaff->id,
            'scheduled_hours' => 6,
            'starts_at' => now()->subDays(1),
            'ends_at' => now()->subDays(1)->addHours(6),
        ]);

        // Finished external engagement 1 (scheduled hours: 5)
        $engExternal1 = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $externalStaff->id,
            'scheduled_hours' => 5,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(5),
        ]);

        // Unfinished/Future internal engagement (should NOT be processed)
        $engFuture = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $internalStaff->id,
            'scheduled_hours' => 8,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(8),
        ]);

        // 3. Pre-create a billing record for an engagement to ensure duplicate processing protection
        $alreadyLoggedEngagement = Engagement::factory()->forEngageable($course)->create([
            'staff_id' => $externalStaff->id,
            'scheduled_hours' => 10,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(10),
        ]);

        DB::table('billing_records')->insert([
            'engagement_id' => $alreadyLoggedEngagement->id,
            'staff_id' => $externalStaff->id,
            'delivered_hours' => 10,
            'total_amount' => 1000,
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        // 4. Run the Artisan command
        $exitCode = Artisan::call('billing:process-daily');
        $this->assertEquals(0, $exitCode);

        // 5. Assertions

        // Unfinished engagement should not have a billing record
        $this->assertFalse(
            DB::table('billing_records')->where('engagement_id', $engFuture->id)->exists()
        );

        // Already logged engagement should not be duplicated
        $this->assertEquals(
            1,
            DB::table('billing_records')->where('engagement_id', $alreadyLoggedEngagement->id)->count()
        );

        // External staff engagement billing amount
        // 5 hours * $100 hourly = 500
        $externalRecord = DB::table('billing_records')
            ->where('engagement_id', $engExternal1->id)
            ->first();

        $this->assertNotNull($externalRecord);
        $this->assertEquals(5, $externalRecord->delivered_hours);
        $this->assertEquals(500, $externalRecord->total_amount);

        // Internal staff billing records
        // One of the internal engagements must have the fixed salary component.
        // Engagement 1: 4 hours * $150 hourly = 600
        // Engagement 2: 6 hours * $150 hourly = 900
        // Total combined amount should be: 600 + 900 + 4000 (fixed_salary) = 5500
        $internalRecords = DB::table('billing_records')
            ->whereIn('engagement_id', [$engInternal1->id, $engInternal2->id])
            ->get();

        $this->assertCount(2, $internalRecords);
        $totalInternalBilled = $internalRecords->sum('total_amount');
        $this->assertEquals(5500, $totalInternalBilled);

        // Verify that only one engagement got the fixed salary added
        $record1 = $internalRecords->firstWhere('engagement_id', $engInternal1->id);
        $record2 = $internalRecords->firstWhere('engagement_id', $engInternal2->id);

        // If record1 had the fixed salary: 4000 + (4 * 150) = 4600 and record2: 6 * 150 = 900
        // Or if record2 had the fixed salary: 4000 + (6 * 150) = 4900 and record1: 4 * 150 = 600
        $validDistribution = (
            ($record1->total_amount === 4600 && $record2->total_amount === 900) ||
            ($record1->total_amount === 600 && $record2->total_amount === 4900)
        );

        $this->assertTrue($validDistribution, 'Fixed salary must be applied to exactly one engagement in the billing month.');
    }
}
