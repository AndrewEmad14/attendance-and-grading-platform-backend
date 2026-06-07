<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\User;
use App\Models\Cohort;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentProfileFactory extends Factory
{
  protected $model = StudentProfile::class;

  public function definition()
  {
    return [
      'user_id' => User::factory()->role('student'),
      'cohort_id' => Cohort::factory(),
      'notes' => $this->faker->sentence(),
      'attendance_balance' => $this->faker->numberBetween(0, 250),
    ];
  }
}
