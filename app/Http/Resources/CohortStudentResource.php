<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CohortStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student_profile_id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->user?->name,
            'email' => $this->user?->email,
            'lab_group_id' => $this->lab_group_id,
            'attendance_balance' => $this->attendance_balance,
        ];
    }
}
