<?php

namespace Database\Factories;

use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

class CohortFactory extends Factory
{
    public function definition(): array
    {
        return [
            'track_id' => Track::factory(),
            'number' => 1,
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function number(int $n): static
    {
        return $this->state(['number' => $n]);
    }
}
