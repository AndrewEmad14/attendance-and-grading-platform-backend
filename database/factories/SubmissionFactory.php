<?php

namespace Database\Factories;

use App\Models\Submission;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubmissionFactory extends Factory
{
  protected $model = Submission::class;

  public function definition()
  {
    return [
      'deliverable_id' => null,
      'student_id' => null,
      'submission_type' => $this->faker->randomElement(['file', 'link']),
      'submission_path' => $this->faker->url(),
      'raw_score' => $this->faker->randomFloat(2, 0, 100),
      'override_score' => 0,
      'override_note' => '',
    ];
  }
}
