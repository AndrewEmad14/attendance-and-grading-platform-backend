<?php

namespace Database\Factories;

use App\Models\LabGroup;
use App\Models\Cohort;
use Illuminate\Database\Eloquent\Factories\Factory;

class LabGroupFactory extends Factory
{
  protected $model = LabGroup::class;

  public function definition()
  {
    return [
      'name' => $this->faker->word() . ' Group',
      'cohort_id' => Cohort::factory(),
    ];
  }
}
