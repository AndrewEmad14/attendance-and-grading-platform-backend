<?php

namespace Database\Factories;

use App\Models\ExcuseRequest;
use App\Models\StudentProfile;
use App\Models\Engagement;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcuseRequestFactory extends Factory
{
  protected $model = ExcuseRequest::class;

  public function definition()
  {
    return [
      'student_id' => StudentProfile::inRandomOrder()->first()->id,
      'engagement_id' => Engagement::inRandomOrder()->first()->id,
      'reason' => $this->faker->paragraph(),
      'attachment_path' => $this->faker->filePath(),
      'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
    ];
  }
}
