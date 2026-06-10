<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

class CohortFactory extends Factory
{
    protected $model = Cohort::class;

    public function definition()
    {
        static $counter = 0;
        $counter++;

        return [
            'number' => $counter,
            'track_id' => Track::factory(),
            'is_active' => false,
        ];
    }

    public function active()
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
