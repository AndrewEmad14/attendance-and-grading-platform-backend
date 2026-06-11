<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExcuseRequestResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'attendance' => $this->whenLoaded('attendanceRecord', fn() => [
        'id' => $this->attendanceRecord->id,
        'arrived_at' => $this->attendanceRecord->arrived_at?->toISOString(),
        'left_at' => $this->attendanceRecord->left_at?->toISOString(),
        'student' => [
          'id' => $this->attendanceRecord->student->id,
          'name' => $this->attendanceRecord->student->user->name,
        ],
        'engagement' => [
          'id' => $this->attendanceRecord->engagement->id,
          'type' => $this->attendanceRecord->engagement->type(),
          'starts_at' => $this->attendanceRecord->engagement->starts_at?->toISOString(),
        ],
      ]),
      'reason' => $this->reason,
      'attachment_path' => $this->attachment_path,
      'status' => $this->status,
      'reviewed_by' => $this->reviewed_by,
      'reviewed_at' => $this->reviewed_at?->toISOString(),
      'created_at' => $this->created_at?->toISOString(),
      'updated_at' => $this->updated_at?->toISOString(),
    ];
  }
}
