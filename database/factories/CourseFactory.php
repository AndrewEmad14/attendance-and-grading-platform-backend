<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Cohort;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
  protected $model = Course::class;

  public function definition()
  {
    return [
      'cohort_id' => Cohort::factory(),
      'name' => $this->faker->sentence(3) . ' Course',
    ];
  }
}
