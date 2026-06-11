<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BranchAnalyticsTest extends TestCase
{
    use DatabaseTransactions;

    // ── Authorization ─────────────────────────────────────────────────────

    public function test_branch_manager_can_access_branch_analytics(): void
    {
        $manager = User::where('role', 'branch_manager')->first()
            ?? User::factory()->branchManager()->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(200);
    }

    public function test_track_admin_is_forbidden(): void
    {
        $user = User::factory()->trackAdmin()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(403);
    }

    public function test_instructor_is_forbidden(): void
    {
        $user = User::factory()->instructor()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(403);
    }

    public function test_student_is_forbidden(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/analytics/branch');
        $response->assertStatus(401);
    }

    // ── Response shape ────────────────────────────────────────────────────

    public function test_response_has_correct_top_level_keys(): void
    {
        $manager = User::where('role', 'branch_manager')->first()
            ?? User::factory()->branchManager()->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'kpis' => [
                    'total_active_students',
                    'total_active_tracks',
                    'branch_attendance_pct',
                    'pass_rate_pct',
                ],
                'tracks',
                'attendance_trend',
            ]);
    }

    public function test_tracks_array_has_correct_shape(): void
    {
        // Only test shape when there are active cohorts
        $hasActiveCohort = Cohort::where('is_active', true)->exists();
        if (! $hasActiveCohort) {
            $this->markTestSkipped('No active cohorts in DB');
        }

        $manager = User::where('role', 'branch_manager')->first()
            ?? User::factory()->branchManager()->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'tracks' => [
                    '*' => [
                        'track_id',
                        'track_name',
                        'cohort_id',
                        'cohort_number',
                        'student_count',
                        'attendance_pct',
                        'total_delivered_hours',
                        'pass_rate_pct',
                        'at_risk_count',
                    ],
                ],
            ]);
    }

    public function test_attendance_trend_by_track_covers_all_active_tracks(): void
    {
        $hasActiveCohort = Cohort::where('is_active', true)->exists();
        if (! $hasActiveCohort) {
            $this->markTestSkipped('No active cohorts in DB');
        }

        $manager = User::where('role', 'branch_manager')->first()
            ?? User::factory()->branchManager()->create();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(200);

        $data = $response->json();
        $activeTrackCount = Cohort::where('is_active', true)
            ->distinct('track_id')
            ->count('track_id');

        if (! empty($data['attendance_trend'])) {
            $firstWeek = $data['attendance_trend'][0];
            $this->assertArrayHasKey('week_start', $firstWeek);
            $this->assertArrayHasKey('branch_pct', $firstWeek);
            $this->assertArrayHasKey('by_track', $firstWeek);
            $this->assertCount($activeTrackCount, $firstWeek['by_track']);
        }
    }

    public function test_kpi_student_count_matches_active_cohort_count(): void
    {
        $manager = User::where('role', 'branch_manager')->first()
            ?? User::factory()->branchManager()->create();

        $activeCohortIds = Cohort::where('is_active', true)->pluck('id');
        $expected = StudentProfile::whereIn('cohort_id', $activeCohortIds)->count();

        $response = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/analytics/branch');

        $response->assertStatus(200)
            ->assertJsonPath('kpis.total_active_students', $expected);
    }
}
