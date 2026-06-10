<?php

namespace Database\Factories;

use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackFactory extends Factory
{
    protected $model = Track::class;

    public function definition()
    {
        static $counter = 0;
        $counter++;

        return [
            'name' => 'Track '.$counter.' - '.$this->faker->word(),
        ];
    }
}
