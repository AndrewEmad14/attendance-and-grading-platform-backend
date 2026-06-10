<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition()
    {
        $role = $this->faker->randomElement(['instructor', 'track_admin', 'branch_manager']);

        return [
            'user_id' => User::factory()->role($role),
            'compensation_type' => $this->faker->randomElement(['internal', 'external']),
            'hourly_rate' => $this->faker->numberBetween(100, 1000),
        ];
    }
}
