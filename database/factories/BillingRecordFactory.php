<?php

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingRecordFactory extends Factory
{
    public function definition(): array
    {
        $hours = $this->faker->randomElement([1, 2, 3]);

        return [
            'engagement_id' => Engagement::factory(),
            'staff_id' => StaffProfile::factory(),
            'delivered_hours' => $hours,
            'total_amount' => $hours * $this->faker->randomElement([100, 150, 200]),
            'forwarded_at' => $this->faker->optional(0.6)->dateTimeBetween('-2 months', 'now'),
        ];
    }
}
