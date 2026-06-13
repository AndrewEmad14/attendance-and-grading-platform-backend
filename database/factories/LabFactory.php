<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\LabGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabFactory extends Factory
{
    public function definition(): array
    {
        $topic = $this->faker->randomElement([
            'CRUD Application',
            'Authentication System',
            'REST API',
            'Database Schema Design',
            'Unit Testing',
            'Frontend Component',
            'Deployment Pipeline',
            'Docker Setup',
            'Git Workflow',
            'Algorithm Challenge',
            'Data Modeling',
            'Security Audit',
            'Performance Optimization',
            'Code Review Exercise',
        ]);

        return [
            'lab_group_id' => LabGroup::factory(),
            'course_id' => Course::factory(),
            'name' => $topic.' Lab',
        ];
    }
}
