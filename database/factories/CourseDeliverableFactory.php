<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseDeliverable;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseDeliverableFactory extends Factory
{
    protected $model = CourseDeliverable::class;

    public function definition()
    {
        return [
            'course_id' => Course::factory(),
            'type' => $this->faker->randomElement(['lab', 'project', 'exam']),
            'name' => $this->faker->sentence(2),
            'max_score' => $this->faker->numberBetween(50, 100),
            'course_weight' => $this->faker->numberBetween(10, 50),
            'due_date' => $this->faker->optional()->dateTimeBetween('+1 week', '+3 months'),
        ];
    }
}
