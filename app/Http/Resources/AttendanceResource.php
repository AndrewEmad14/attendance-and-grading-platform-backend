<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray (Request $request): array
    {
        return [
            'id' => $this->id,
            'engagement' => $this->whenLoaded('engagement', fn () => [
                'id' => $this->engagement->id,
                'type' => $this->engagement->type(),
                'starts_at' => $this->engagement->starts_at?->toISOString(),
                'ends_at' => $this->engagement->ends_at?->toISOString(),
                'scheduled_hours' => $this->engagement->scheduled_hours,
                'staff_id' => $this->engagement->staff_id,
                'engageable_id' => $this->engagement->engageable_id,
                'engageable_type' => $this->engagement->engageable_type,
            ]),
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->user->name,
                'cohort_id' => $this->student->cohort_id,
                'lab_group_id' => $this->student->lab_group_id,
                'attendance_balance' => $this->student->attendance_balance,
            ]),
            'arrived_at' => $this->arrived_at?->toISOString(),
            'left_at' => $this->left_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
