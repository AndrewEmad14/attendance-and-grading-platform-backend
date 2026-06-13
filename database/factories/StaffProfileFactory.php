<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'compensation_type' => $this->faker->randomElement(['internal', 'external']),
            'hourly_rate' => $this->faker->randomElement([100, 150, 200, 250]),
            'fixed_salary' => $this->faker->optional(0.5)->randomElement([8000, 10000, 12000, 15000]),
        ];
    }
}
