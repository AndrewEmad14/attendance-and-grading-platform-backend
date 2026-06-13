<?php

namespace Database\Factories;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Lab;
use App\Models\StaffProfile;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

class EngagementFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = $this->faker->dateTimeBetween('-1 year', '+3 months');
        $hours = $this->faker->randomElement([1, 2, 3]);

        return [
            'engageable_id' => 1,
            'engageable_type' => Course::class,
            'staff_id' => StaffProfile::factory(),
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify("+{$hours} hours"),
            'scheduled_hours' => $hours,
            'absences_processed_at' => null,
        ];
    }

    /**
     * Attach to a specific engageable model (Course, Lab, BusinessSession).
     */
    public function forEngageable(Model $model): static
    {
        return $this->state([
            'engageable_id' => $model->id,
            'engageable_type' => get_class($model),
        ]);
    }

    /**
     * Set engagement in a specific year, on a specific weekday slot.
     * $slot: 0 = 9am-11am, 1 = 11am-1pm, 2 = 2pm-4pm
     */
    public function inYear(int $year, Carbon $date, int $slot = 0): static
    {
        $slotTimes = [
            0 => ['09:00', '12:00', 2],
            1 => ['12:30', '15:30', 2],
            2 => ['16:00', '19:00', 3],
        ];

        [$startTime, $endTime, $hours] = $slotTimes[$slot];

        $startsAt = $date->copy()->setTimeFromTimeString($startTime);
        $endsAt = $date->copy()->setTimeFromTimeString($endTime);

        return $this->state([
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'scheduled_hours' => $hours,
        ]);
    }

    /**
     * Mark as already processed (past engagement).
     */
    public function processed(): static
    {
        return $this->state([
            'absences_processed_at' => now(),
        ]);
    }
}
