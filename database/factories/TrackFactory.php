<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class TrackFactory extends Factory
{
    // 35 realistic ITI-style track names
    private static array $trackNames = [
        'Web Development',
        'Mobile Development',
        'Data Science',
        'Artificial Intelligence',
        'Cloud Computing',
        'DevOps Engineering',
        'Cybersecurity',
        'Full Stack .NET',
        'Full Stack Java',
        'Full Stack Python',
        'UI/UX Design',
        'Business Intelligence',
        'Database Administration',
        'Network Engineering',
        'Embedded Systems',
        'Internet of Things',
        'Blockchain Development',
        'Game Development',
        'AR/VR Development',
        'Quality Assurance',
        'Technical Support',
        'IT Project Management',
        'Digital Marketing Technology',
        'E-Commerce Development',
        'Machine Learning Engineering',
        'Data Engineering',
        'Systems Administration',
        'Software Testing',
        'API Development',
        'React Native Development',
        'Flutter Development',
        'Node.js Development',
        'PHP & Laravel Development',
        'Python Django Development',
        'Ruby on Rails Development',
    ];

    private static int $nameIndex = 0;

    public function definition(): array
    {
        $name = self::$trackNames[self::$nameIndex % count(self::$trackNames)];
        self::$nameIndex++;

        return ['name' => $name];
    }
}
