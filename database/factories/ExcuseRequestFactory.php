<?php

namespace Database\Factories;

use App\Models\Engagement;
use App\Models\ExcuseRequest;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcuseRequestFactory extends Factory
{
    protected $model = ExcuseRequest::class;

    private static array $reasons = [
        'I had a medical emergency and had to visit the hospital.',
        'My family had an urgent situation that required my presence.',
        'I was involved in a car accident on the way to the institute.',
        'I suffered from a severe migraine that left me unable to attend.',
        'There was a public transport strike and I had no way to commute.',
        'I had a scheduled surgery that could not be postponed.',
        'A close family member passed away and I was attending the funeral.',
        'I had a government appointment that was set months in advance.',
        'My child was ill and required my care as the sole guardian.',
        'I experienced a sudden power outage that disrupted my remote connection.',
        'There was a flood in my area that made roads impassable.',
        'I was hospitalised overnight and discharged the following day.',
        'I had a critical exam for a certification course on the same day.',
        'My accommodation caught fire and I was dealing with the aftermath.',
        'I had an unavoidable visa appointment at the embassy.',
    ];

    public function definition(): array
    {
        return [
            'engagement_id' => Engagement::factory(),
            'student_id' => StudentProfile::factory(),
            'reason' => $this->faker->randomElement(self::$reasons),
            'attachment_path' => '/excuse-attachments/'.$this->faker->uuid().'.pdf',
            'status' => ExcuseRequest::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ExcuseRequest::STATUS_APPROVED,
            'reviewed_by' => StaffProfile::factory(),
            'reviewed_at' => now()->subDays(rand(1, 5)),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn () => [
            'status' => ExcuseRequest::STATUS_REJECTED,
            'reviewed_by' => StaffProfile::factory(),
            'reviewed_at' => now()->subDays(rand(1, 5)),
        ]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => ExcuseRequest::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);
    }
}
