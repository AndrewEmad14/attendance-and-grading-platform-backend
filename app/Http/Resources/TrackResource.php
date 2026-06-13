<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TrackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Conditional data metrics to avoid N+1 aggregation loads
            'cohorts_count' => $this->whenCounted('cohorts'),
            'cohorts' => CohortResource::collection($this->whenLoaded('cohorts')),
        ];
    }
}
