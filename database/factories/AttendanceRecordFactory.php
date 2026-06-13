<?php

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\StudentProfile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceRecordFactory extends Factory
{
    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'student_id' => StudentProfile::factory(),
            'arrived_at' => null,
            'left_at' => null,
        ];
    }

    /**
     * Student was present — arrived a few minutes after start, left at end.
     */
    public function present(Carbon $engagementStart, Carbon $engagementEnd): static
    {
        $arrivedAt = $engagementStart->copy()->addMinutes($this->faker->numberBetween(0, 15));
        $leftAt = $engagementEnd->copy()->subMinutes($this->faker->numberBetween(0, 10));

        return $this->state([
            'arrived_at' => $arrivedAt,
            'left_at' => $leftAt,
        ]);
    }

    /**
     * Student checked in but never checked out (e.g. forgot).
     */
    public function checkedInOnly(Carbon $engagementStart): static
    {
        return $this->state([
            'arrived_at' => $engagementStart->copy()->addMinutes($this->faker->numberBetween(0, 20)),
            'left_at' => null,
        ]);
    }
}
