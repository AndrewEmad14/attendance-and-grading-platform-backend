<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BusinessSessionFactory extends Factory
{
    private static array $sessionNames = [
        'Career Fair',
        'Alumni Panel',
        'Industry Guest Lecture',
        'Graduation Ceremony',
        'CV Workshop',
        'Mock Interviews',
        'Networking Event',
        'Hackathon Kickoff',
        'Open Day',
        'Job Fair',
        'Tech Talk: AI Trends',
        'Tech Talk: Cloud Native',
        'Tech Talk: Cybersecurity',
        'Soft Skills Workshop',
        'Entrepreneurship Talk',
        'Company Visit: Tech Corp',
        'Company Visit: Startup Hub',
        'Portfolio Review Session',
        'Capstone Presentations',
        'Awards Ceremony',
    ];

    private static int $index = 0;

    public function definition(): array
    {
        $name = self::$sessionNames[self::$index % count(self::$sessionNames)];
        self::$index++;

        return ['name' => $name];
    }
}
