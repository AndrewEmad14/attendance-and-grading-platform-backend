<?php

namespace Database\Factories;

use App\Models\AttendanceRecord;
use App\Models\ExcuseRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExcuseRequestFactory extends Factory
{
    protected $model = ExcuseRequest::class;

    public function definition()
    {
        return [
            'attendance_id' => AttendanceRecord::factory(),
            'reason' => $this->faker->paragraph(),
            'attachment_path' => $this->faker->filePath(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }
}
