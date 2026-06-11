<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Course;
use App\Models\Engagement;
use App\Models\StudentProfile;
use App\Models\ExcuseRequest;
use App\Services\AttendanceLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessAbsenceTest extends TestCase
{
  use RefreshDatabase;

  protected User $studentUser;
  protected StudentProfile $studentProfile;
  protected Engagement $engagement1;
  protected Engagement $engagement2;
  protected AttendanceLedgerService $ledgerService;

  protected function setUp(): void
  {
    parent::setUp();

    $this->ledgerService = app(AttendanceLedgerService::class);

    // 1. Provision Student Profile linked to a cohort
    $this->studentUser = User::factory()->create(['role' => 'student']);
    $this->studentProfile = StudentProfile::factory()->create([
      'user_id' => $this->studentUser->id,
      'attendance_balance' => 220, // Arbitrary current balance snapshot
    ]);

    $cohortId = $this->studentProfile->cohort_id;

    // 2. Create two lecture engagements that this cohort must attend
    $course1 = Course::factory()->create(['cohort_id' => $cohortId]);
    $this->engagement1 = Engagement::factory()->create([
      'engageable_type' => Course::class,
      'engageable_id' => $course1->id,
      'starts_at' => now()->subDays(2),
    ]);

    $course2 = Course::factory()->create(['cohort_id' => $cohortId]);
    $this->engagement2 = Engagement::factory()->create([
      'engageable_type' => Course::class,
      'engageable_id' => $course2->id,
      'starts_at' => now()->subDay(),
    ]);
  }

  public function test_ledger_calculates_correct_deductions_for_unexcused_vs_excused_absences(): void
  {
    // Engagement 1: Left unexcused (No AttendanceRecord, No ExcuseRequest)

    // Engagement 2: Marked Absent but has an APPROVED excuse
    ExcuseRequest::factory()->create([
      'student_id' => $this->studentProfile->id,
      'engagement_id' => $this->engagement2->id,
      'status' => 'approved',
    ]);

    // Process ledger generation
    $ledger = $this->ledgerService->buildLedger($this->studentProfile);

    $entries = collect($ledger['entries']);

    // Find ledger entry for Engagement 1 (Unexcused)
    $unexcusedEntry = $entries->firstWhere('engagement_id', $this->engagement1->id);
    $this->assertNotNull($unexcusedEntry);
    $this->assertEquals('absent', $unexcusedEntry['absence_status']);
    $this->assertEquals('none', $unexcusedEntry['excuse_status']);
    $this->assertEquals(-25, $unexcusedEntry['deduction']); // -25 deduction rule

    // Find ledger entry for Engagement 2 (Excused)
    $excusedEntry = $entries->firstWhere('engagement_id', $this->engagement2->id);
    $this->assertNotNull($excusedEntry);
    $this->assertEquals('absent', $excusedEntry['absence_status']);
    $this->assertEquals('approved', $excusedEntry['excuse_status']);
    $this->assertEquals(-5, $excusedEntry['deduction']); // -5 deduction rule

    // Starting balance is 250. 
    // Entry 1 (Unexcused): 250 - 25 = 225 running balance
    // Entry 2 (Excused): 225 - 5 = 220 running balance
    $this->assertEquals(220, $excusedEntry['running_balance']);
  }
}
