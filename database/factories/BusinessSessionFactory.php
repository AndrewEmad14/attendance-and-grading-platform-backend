<?php

namespace Database\Factories;

use App\Models\BusinessSession;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessSessionFactory extends Factory
{
    protected $model = BusinessSession::class;

    public function definition()
    {
        return [
            'name' => $this->faker->company().' Session',
        ];
    }
}
