<?php

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class EngagementFactory extends Factory
{
    protected $model = Engagement::class;

    public function definition()
    {
        return [
            'staff_id' => StaffProfile::factory(),
            'engageable_type' => null,
            'engageable_id' => null,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
            'scheduled_hours' => 1,
        ];
    }

    public function forEngageable($engageable)
    {
        return $this->state([
            'engageable_id' => $engageable->id,
            'engageable_type' => $engageable::class,
        ]);
    }
}
