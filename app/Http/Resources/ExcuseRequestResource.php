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
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student->id,
                'name' => $this->student->user?->name,
            ]),
            'engagement' => $this->whenLoaded('engagement', fn () => [
                'id' => $this->engagement->id,
                'type' => $this->engagement->type,
                'name' => $this->engagement->engageable?->name ?? "Engagement #{$this->engagement->id}",
                'starts_at' => $this->engagement->starts_at?->toISOString(),
                'ends_at' => $this->engagement->ends_at?->toISOString(),
                'instructor' => $this->engagement->staff?->user?->name ?? null,
            ]),
            'reason' => $this->reason,
            'status' => $this->status,
            'review' => $this->reviewed_by ? [
                'by' => $this->whenLoaded('reviewer', fn () => [
                    'id' => $this->reviewer->id,
                    'name' => $this->reviewer->user?->name,
                ], [
                    'id' => $this->reviewed_by,
                ]),
                'at' => $this->reviewed_at?->toISOString(),
                'note' => $this->review_note,
            ] : null,

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'attachment_url' => $this->attachment_path ? asset('storage/'.ltrim($this->attachment_path, '/')) : null,
        ];
    }
}
