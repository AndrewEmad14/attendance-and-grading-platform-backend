<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementFactory extends Factory
{
  protected $model = Announcement::class;

  public function definition()
  {
    return [
      'title' => $this->faker->sentence(),
      'body' => $this->faker->paragraphs(3, true),
      'staff_id' => null,
      'cohort_id' => null,
      'published_at' => $this->faker->optional(0.9)->dateTimeBetween('-1 month', '+1 week'),
    ];
  }

  public function global()
  {
    return $this->state(['cohort_id' => null]);
  }

  public function forCohort($cohortId)
  {
    return $this->state(['cohort_id' => $cohortId]);
  }
}
