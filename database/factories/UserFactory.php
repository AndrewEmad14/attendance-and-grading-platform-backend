<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password_hash' => bcrypt('password'),
            'role' => 'student',
            'expires_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function student(): static
    {
        return $this->state(['role' => 'student']);
    }

    public function instructor(): static
    {
        return $this->state(['role' => 'instructor']);
    }

    public function trackAdmin(): static
    {
        return $this->state(['role' => 'track_admin']);
    }

    public function branchManager(): static
    {
        return $this->state(['role' => 'branch_manager']);
    }
}
