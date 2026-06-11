<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Engagement;
use App\Models\Course;
use App\Models\StudentProfile;
use App\Models\StaffProfile;
use App\Models\ExcuseRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExcuseRequestTest extends TestCase
{
  use RefreshDatabase;

  protected User $studentUser;
  protected StudentProfile $studentProfile;
  protected User $branchManager;
  protected StaffProfile $managerProfile;
  protected Engagement $engagement;

  protected function setUp(): void
  {
    parent::setUp();

    // 1. Provision Student User and Profile
    $this->studentUser = User::factory()->create(['role' => 'student']);
    $this->studentProfile = StudentProfile::factory()->create([
      'user_id' => $this->studentUser->id,
      'attendance_balance' => 250,
    ]);

    // 2. Provision Staff User (Synchronized with student branch context for AccessService validation)
    $this->branchManager = User::factory()->create(['role' => 'branch_manager']);
    $this->managerProfile = StaffProfile::factory()->create([
      'user_id'   => $this->branchManager->id,
      'branch_id' => $this->studentProfile->branch_id ?? null,
    ]);

    // 3. Provision an Engagement
    $course = Course::factory()->create();
    $this->engagement = Engagement::factory()->create([
      'engageable_type' => Course::class,
      'engageable_id' => $course->id,
    ]);
  }

  public function test_student_can_successfully_submit_an_excuse_request(): void
  {
    Storage::fake('public');

    $payload = [
      'engagement_id'   => $this->engagement->id,
      'reason'          => 'Feeling unwell, attended medical appointment.',
      'attachment'      => UploadedFile::fake()->create('medical_slip.pdf', 200),
      'attachment_path' => 'excuses/fake_path.pdf' // Provides a fallback string in case your service maps raw parameters directly
    ];

    $this->actingAs($this->studentUser, 'sanctum')
      ->postJson('/api/excuse-requests', $payload)
      ->assertStatus(201);

    $this->assertDatabaseHas('excuse_requests', [
      'student_id'    => $this->studentProfile->id,
      'engagement_id' => $this->engagement->id,
      'status'        => 'pending',
    ]);
  }

  public function test_student_cannot_submit_duplicate_excuse_for_same_engagement(): void
  {
    ExcuseRequest::factory()->create([
      'student_id'    => $this->studentProfile->id,
      'engagement_id' => $this->engagement->id,
      'status'        => 'pending',
    ]);

    $payload = [
      'engagement_id' => $this->engagement->id,
      'reason'        => 'Another reason trying to bypass restriction.',
    ];

    $this->actingAs($this->studentUser, 'sanctum')
      ->postJson('/api/excuse-requests', $payload)
      ->assertStatus(422);
  }

  public function test_approving_an_excuse_restores_twenty_points_to_student_balance(): void
  {
    $excuseRequest = ExcuseRequest::factory()->create([
      'student_id'    => $this->studentProfile->id,
      'engagement_id' => $this->engagement->id,
      'status'        => 'pending',
    ]);

    $this->assertEquals(250, $this->studentProfile->fresh()->attendance_balance);

    $this->actingAs($this->branchManager, 'sanctum')
      ->postJson("/api/excuse-requests/{$excuseRequest->id}/approve")
      ->assertStatus(200);

    $this->assertDatabaseHas('excuse_requests', [
      'id'          => $excuseRequest->id,
      'status'      => 'approved',
      'reviewed_by' => $this->managerProfile->id,
    ]);

    $this->assertEquals(270, $this->studentProfile->fresh()->attendance_balance);
  }

  public function test_cannot_review_an_already_processed_excuse_request(): void
  {
    $processedExcuse = ExcuseRequest::factory()->create([
      'student_id'    => $this->studentProfile->id,
      'engagement_id' => $this->engagement->id,
      'status'        => 'approved',
      'reviewed_by'   => $this->managerProfile->id,
    ]);

    $this->actingAs($this->branchManager, 'sanctum')
      ->postJson("/api/excuse-requests/{$processedExcuse->id}/reject")
      ->assertStatus(422);
  }
}
