<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CohortResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'track_id' => $this->track_id,
            'number' => $this->number,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Conditional
            'track' => new TrackResource($this->whenLoaded('track')),
            'students_count' => $this->whenCounted('students'),
            'admins' => TrackAdminResource::collection($this->whenLoaded('admins')),
        ];
    }
}
