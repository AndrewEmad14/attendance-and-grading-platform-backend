<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'course_id' => $this->course_id,
            'lab_group_id' => $this->lab_group_id,
            'lab_group' => new LabGroupResource($this->whenLoaded('labGroup')),
        ];
    }
}
