<?php

namespace Database\Factories;

use App\Models\BillingRecord;
use App\Models\Engagement;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class BillingRecordFactory extends Factory
{
    protected $model = BillingRecord::class;

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'staff_id' => StaffProfile::factory(),
            'delivered_hours' => $this->faker->numberBetween(1, 4),
            'total_amount' => $this->faker->numberBetween(100, 1000),
            'forwarded_at' => $this->faker->optional(0.5)->dateTimeBetween('-1 month', 'now'),
        ];
    }
}
