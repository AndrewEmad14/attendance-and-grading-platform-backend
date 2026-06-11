<?php

namespace Database\Factories;

use App\Models\Cohort;
use App\Models\LabGroup;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    public function definition()
    {
        return [
            'user_id' => User::factory()->role('student'),
            'cohort_id' => Cohort::factory(),
            'lab_group_id' => function (array $attributes) {
                return LabGroup::factory()->create([
                    'cohort_id' => $attributes['cohort_id'],
                ])->id;
            },
            'notes' => $this->faker->sentence(),
            'attendance_balance' => 250
        ];
    }
}
