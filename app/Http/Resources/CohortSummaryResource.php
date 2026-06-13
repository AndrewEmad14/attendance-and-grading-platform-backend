<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TrackSummaryResource;

class CohortSummaryResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'number'    => $this->number,
            'is_active' => $this->is_active,
            'track'     => new TrackSummaryResource($this->whenLoaded('track')),
            // track_id removed — track.id already has it
        ];
    }
}
