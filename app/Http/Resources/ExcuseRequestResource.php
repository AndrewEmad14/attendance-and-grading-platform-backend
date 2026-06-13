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
            'student' => $this->whenLoaded('student', fn() => [
                'id' => $this->student->id,
                'name' => $this->student->user->name,
            ]),
            'engagement' => $this->whenLoaded('engagement', fn() => [
                'id' => $this->engagement->id,
                'type' => $this->engagement->type,
                'starts_at' => $this->engagement->starts_at?->toISOString(),
            ]),
            'reason' => $this->reason,
            'status' => $this->status,
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'attachment_url' => $this->attachment_path ? asset('storage/' . $this->attachment_path) : null,
        ];
    }
}
