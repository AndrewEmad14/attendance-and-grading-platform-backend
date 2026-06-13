<?php

namespace Database\Factories;

use App\Models\CourseDeliverable;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'deliverable_id' => CourseDeliverable::factory(),
            'student_id' => StudentProfile::factory(),
            'submission_type' => $this->faker->randomElement(['file', 'link']),
            'submission_path' => 'https://github.com/student/'.$this->faker->word().'-project',
            'raw_score' => $this->faker->randomFloat(1, 10, 100),
            'graded_by' => null,
            'override_score' => null,
            'overridden_by' => null,
            'override_note' => null,
            'overridden_at' => null,
        ];
    }

    public function graded(int $staffId, float $maxScore): static
    {
        return $this->state([
            'raw_score' => $this->faker->randomFloat(1, $maxScore * 0.4, $maxScore),
            'graded_by' => $staffId,
        ]);
    }
}
