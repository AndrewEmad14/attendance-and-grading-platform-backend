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
      'attendance_id' => $this->attendance_id,
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
