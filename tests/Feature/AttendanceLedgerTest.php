<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUserA;

    protected StudentProfile $studentProfileA;

    protected User $studentUserB;

    protected StudentProfile $studentProfileB;

    protected User $trackAdminUser;

    protected StaffProfile $trackAdminProfile;

    protected Cohort $cohort;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cohort = Cohort::factory()->create();

        // 1. Provision Student A and profile
        $this->studentUserA = User::factory()->create(['role' => 'student']);
        $this->studentProfileA = StudentProfile::factory()->create([
            'user_id' => $this->studentUserA->id,
            'cohort_id' => $this->cohort->id,
            'attendance_balance' => 250,
        ]);

        // 2. Provision Student B and profile (for cross-snooping checks)
        $this->studentUserB = User::factory()->create(['role' => 'student']);
        $this->studentProfileB = StudentProfile::factory()->create([
            'user_id' => $this->studentUserB->id,
            'cohort_id' => $this->cohort->id,
            'attendance_balance' => 250,
        ]);

        // 3. Provision Track Admin and associate their profile row
        $this->trackAdminUser = User::factory()->create(['role' => 'track_admin']);
        $this->trackAdminProfile = StaffProfile::factory()->create([
            'user_id' => $this->trackAdminUser->id,
        ]);

        // 4. Connect Track Admin to the student's cohort to satisfy AccessService verification
        if (method_exists($this->cohort->trackAdmins(), 'attach')) {
            $this->cohort->trackAdmins()->attach($this->trackAdminProfile->id);
        } else {
            $this->cohort->trackAdmins()->save($this->trackAdminProfile);
        }
    }

    public function test_unauthenticated_users_cannot_view_ledgers(): void
    {
        $this->getJson("/api/students/{$this->studentProfileA->id}/attendance-ledger")
            ->assertStatus(401);
    }

    public function test_student_can_view_their_own_attendance_ledger(): void
    {
        $this->actingAs($this->studentUserA, 'sanctum')
            ->getJson("/api/students/{$this->studentProfileA->id}/attendance-ledger")
            ->assertStatus(200);
    }

    public function test_student_cannot_view_another_students_attendance_ledger(): void
    {
        $this->actingAs($this->studentUserA, 'sanctum')
            ->getJson("/api/students/{$this->studentProfileB->id}/attendance-ledger")
            ->assertStatus(403);
    }

    public function test_authorized_staff_can_view_any_students_attendance_ledger(): void
    {
        $this->actingAs($this->trackAdminUser, 'sanctum')
            ->getJson("/api/students/{$this->studentProfileA->id}/attendance-ledger")
            ->assertStatus(200);
    }
}
