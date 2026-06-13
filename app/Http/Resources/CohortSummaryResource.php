<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CohortSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'is_active' => $this->is_active,
            'track' => new TrackSummaryResource($this->whenLoaded('track')),
            // track_id removed — track.id already has it
        ];
    }
}
