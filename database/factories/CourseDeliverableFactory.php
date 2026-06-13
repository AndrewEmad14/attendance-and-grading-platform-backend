<?php

namespace Database\Factories;

use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseDeliverableFactory extends Factory
{
    private static array $deliverableNames = [
        'Lab 1: Environment Setup',
        'Lab 2: Basic CRUD',
        'Lab 3: Authentication',
        'Lab 4: REST API',
        'Lab 5: Database Relations',
        'Lab 6: Testing',
        'Lab 7: Deployment',
        'Midterm Project',
        'Final Project',
        'Quiz 1',
        'Quiz 2',
        'Assignment: Code Review',
        'Assignment: Documentation',
        'Assignment: System Design',
        'Capstone Presentation',
    ];

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'type' => $this->faker->randomElement(['project', 'exam', 'lab']),
            'name' => $this->faker->randomElement(self::$deliverableNames),
            'max_score' => $this->faker->randomElement([50, 100]),
            'course_weight' => $this->faker->randomElement([5, 10, 15, 20, 25, 30]),
            'due_date' => $this->faker->dateTimeBetween('-6 months', '+3 months'),
        ];
    }
}
