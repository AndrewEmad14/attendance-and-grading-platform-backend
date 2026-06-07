<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceRecordFactory extends Factory
{
  protected $model = AttendanceRecord::class;

  public function definition()
  {
    return [
      'engagement_id' => null,
      'student_id' => null,
      'arrived_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
      'left_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
    ];
  }
}
