<?php

namespace Database\Factories;

use App\Models\Cohort;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cohort_id' => Cohort::factory(),
            'name' => 'Group '.$this->faker->randomElement(['Alpha', 'Beta', 'Gamma', 'Delta', 'Epsilon', 'Zeta', 'Eta', 'Theta']),
        ];
    }
}
