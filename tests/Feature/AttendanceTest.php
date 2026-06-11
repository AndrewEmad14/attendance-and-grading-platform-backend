<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Engagement;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $studentUser;

    protected StudentProfile $studentProfile;

    protected Engagement $engagement;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create a Student User and explicitly bind their StudentProfile
        $this->studentUser = User::factory()->create(['role' => 'student']);
        $this->studentProfile = StudentProfile::factory()->create([
            'user_id' => $this->studentUser->id,
            'attendance_balance' => 250,
        ]);

        // 2. Set up an engagement with an active session window to satisfy time checks
        $course = Course::factory()->create();
        $this->engagement = Engagement::factory()->create([
            'engageable_type' => Course::class,
            'engageable_id' => $course->id,
            'starts_at' => now()->subMinutes(10),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function test_unauthenticated_users_cannot_access_attendance_apis(): void
    {
        $this->postJson('/api/attendance', ['engagement_id' => $this->engagement->id])
            ->assertStatus(401);
    }

    public function test_student_can_successfully_scan_to_check_in(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/attendance', [
                'engagement_id' => $this->engagement->id,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('attendance_records', [
            'engagement_id' => $this->engagement->id,
            'student_id' => $this->studentProfile->id,
        ]);
    }

    public function test_student_cannot_check_in_outside_active_session_window(): void
    {
        $expiredEngagement = Engagement::factory()->create([
            'engageable_type' => Course::class,
            'engageable_id' => Course::factory()->create()->id,
            'starts_at' => now()->subHours(5),
            'ends_at' => now()->subHours(4),
        ]);

        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/attendance', [
                'engagement_id' => $expiredEngagement->id,
            ])
            ->assertStatus(422);
    }

    public function test_validation_fails_if_engagement_id_is_invalid(): void
    {
        $this->actingAs($this->studentUser, 'sanctum')
            ->postJson('/api/attendance', [
                'engagement_id' => 'not-an-integer',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['engagement_id']);
    }
}
