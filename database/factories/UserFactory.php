<?php

namespace Database\Factories;

use App\Enums\CompensationType;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password_hash' => Hash::make('password'),
            'role' => 'student',
            'expires_at' => $this->faker->optional(0.2)->dateTimeBetween('+1 month', '+1 year'),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(string $role)
    {
        return $this->state(fn (array $attributes) => ['role' => $role]);
    }

    public function branchManager()
    {
        return $this->state(['role' => 'branch_manager'])
            ->afterCreating(function (User $user) {
                StaffProfile::factory()->create([
                    'user_id' => $user->id,
                    'compensation_type' => CompensationType::INTERNAL->value,
                    'fixed_salary' => 0,
                    'hourly_rate' => 0,
                ]);
            });
    }

    public function trackAdmin()
    {
        return $this->state(['role' => 'track_admin']);
    }

    public function instructor()
    {
        return $this->state(['role' => 'instructor']);
    }

    public function student()
    {
        return $this->state(['role' => 'student']);
    }
}
