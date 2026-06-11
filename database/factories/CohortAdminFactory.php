<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\CohortAdmin;
use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class CohortAdminFactory extends Factory
{
    protected $model = CohortAdmin::class;

    public function definition(): array
    {
        return [
            'cohort_id' => Cohort::factory(),
            'staff_id' => StaffProfile::factory(),
        ];
    }

    /**
     * Indicate that the admin is assigned to a specific cohort.
     */
    public function forCohort(Cohort $cohort): static
    {
        return $this->state(fn (array $attributes) => [
            'cohort_id' => $cohort->id,
        ]);
    }

    /**
     * Indicate that the admin is a specific staff profile.
     */
    public function forStaff(StaffProfile $staff): static
    {
        return $this->state(fn (array $attributes) => [
            'staff_id' => $staff->id,
        ]);
    }
}
