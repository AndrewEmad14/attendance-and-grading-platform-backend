<?php

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\ExcuseRequest;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcuseRequestFactory extends Factory
{
  protected $model = ExcuseRequest::class;

  public function definition(): array
  {
    return [
      'student_id' => StudentProfile::factory(),
      'engagement_id' => Engagement::factory(),
      'reason' => $this->faker->paragraph(),
      'attachment_path' => $this->faker->filePath(),
      'status' => ExcuseRequest::STATUS_PENDING,
      'reviewed_by' => null,
      'reviewed_at' => null,
    ];
  }

  public function approved(): static
  {
    return $this->state(fn() => [
      'status' => ExcuseRequest::STATUS_APPROVED,
      'reviewed_by' => StaffProfile::factory(),
      'reviewed_at' => now(),
    ]);
  }

  public function rejected(): static
  {
    return $this->state(fn() => [
      'status' => ExcuseRequest::STATUS_REJECTED,
      'reviewed_by' => StaffProfile::factory(),
      'reviewed_at' => now(),
    ]);
  }
}
