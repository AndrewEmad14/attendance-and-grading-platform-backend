<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'cohort_id' => null,
            'lab_group_id' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'attendance_balance' => 250,
        ];
    }
}
