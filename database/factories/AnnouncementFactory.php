<?php

namespace Database\Factories;

use App\Models\StaffProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class AnnouncementFactory extends Factory
{
    private static array $titles = [
        'Schedule Change Notice',
        'Holiday Announcement',
        'Important: Attendance Policy Reminder',
        'Upcoming Career Fair',
        'Lab Access Update',
        'Exam Schedule Released',
        'Guest Speaker This Week',
        'Project Submission Deadline Extended',
        'New Resources Available on Portal',
        'Graduation Requirements Update',
        'Mentorship Program Open',
        'Hackathon Registration Open',
        'System Maintenance Window',
        'Grading Completed: Check Your Scores',
        'Welcome to the New Cohort',
    ];

    public function definition(): array
    {
        return [
            'cohort_id' => null,
            'staff_id' => StaffProfile::factory(),
            'title' => $this->faker->randomElement(self::$titles),
            'body' => $this->faker->paragraphs(2, true),
            'published_at' => $this->faker->optional(0.8)->dateTimeBetween('-3 months', 'now'),
        ];
    }

    public function global(): static
    {
        return $this->state(['cohort_id' => null]);
    }

    public function forCohort(int $cohortId): static
    {
        return $this->state(['cohort_id' => $cohortId]);
    }
}
