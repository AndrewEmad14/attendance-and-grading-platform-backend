<?php

namespace Database\Factories;

use App\Models\Submission;
use App\Models\CourseDeliverable;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
  protected $model = Submission::class;

  public function definition()
  {
    return [
      'deliverable_id' => CourseDeliverable::factory(),
      'student_id' => StudentProfile::factory(),
      'submission_type' => $this->faker->randomElement(['file', 'link']),
      'submission_path' => $this->faker->url(),
      'raw_score' => $this->faker->randomFloat(2, 0, 100),
      'graded_by' => null,
      'override_score' => null,
      'override_note' => null,
      'overriden_by' => null,
      'overriden_at' => null,
    ];
  }
}
