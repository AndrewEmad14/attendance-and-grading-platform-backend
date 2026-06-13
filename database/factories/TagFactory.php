<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    private static array $tags = [
        'at-risk',
        'high-performer',
        'needs-mentoring',
        'scholarship',
        'part-time',
        'international',
        'career-changer',
        'repeat-student',
        'peer-mentor',
        'industry-sponsored',
        'special-needs',
        'fast-tracker',
        'alumni-referral',
        'remote',
        'on-probation',
    ];

    private static int $index = 0;

    public function definition(): array
    {
        $tag = self::$tags[self::$index % count(self::$tags)];
        self::$index++;

        return ['tag' => $tag];
    }
}
