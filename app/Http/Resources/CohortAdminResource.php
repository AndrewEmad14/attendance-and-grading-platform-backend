<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\CohortSummaryResource;

class CohortAdminResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'cohort' => new CohortSummaryResource($this->whenLoaded('cohort')),
        ];
    }
}