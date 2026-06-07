<?php

namespace Database\Factories;

use App\Models\Engagement;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngagementFactory extends Factory
{
  protected $model = Engagement::class;

  public function definition()
  {
    return [
      'engageable_id' => null,
      'staff_id' => null,
      'type' => 'lecture',
      'starts_at' => now(),
      'ends_at' => now()->addHour(),
      'scheduled_hours' => 1,
    ];
  }

  public function forEngageable($engageable)
  {
    $type = match ($engageable::class) {
      'App\Models\Course' => 'lecture',
      'App\Models\Lab' => 'lab',
      'App\Models\BusinessSession' => 'business_session',
      default => 'lecture',
    };

    return $this->state([
      'engageable_id' => $engageable->id,
      'type' => $type,
    ]);
  }
}
