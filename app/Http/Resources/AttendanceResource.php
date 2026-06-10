<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
  public function toArray(Request $request): array
  {
    return [
      'id' => $this->id,
      'engagement_id' => $this->engagement_id,
      'student' => [
        'id' => $this->student->id,
        'name' => $this->student->user->name,
      ],
      'arrived_at' => $this->arrived_at?->toISOString(),
      'left_at' => $this->left_at?->toISOString(),
      'created_at' => $this->created_at?->toISOString(),
      'updated_at' => $this->updated_at?->toISOString(),
    ];
  }
}
