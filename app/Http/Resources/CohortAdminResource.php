<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CohortAdminResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'cohort' => new CohortSummaryResource($this->whenLoaded('cohort')),
        ];
    }
}
