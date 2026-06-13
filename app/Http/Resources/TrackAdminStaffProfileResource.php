<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CohortAdminResource;

class TrackAdminStaffProfileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'              => $this->id,
            'user_id'         => $this->user_id,
            'managed_cohorts' => CohortAdminResource::collection($this->whenLoaded('managedCohorts')),
        ];
    }
}
