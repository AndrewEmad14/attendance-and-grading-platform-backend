<?php

namespace Database\Factories;

use App\Models\Lab;
use App\Models\LabGroup;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabFactory extends Factory
{
  protected $model = Lab::class;

  public function definition()
  {
    return [
      'name' => $this->faker->word() . ' Lab',
      'lab_group_id' => LabGroup::factory(),
      'course_id' => Course::factory(),
    ];
  }
}
