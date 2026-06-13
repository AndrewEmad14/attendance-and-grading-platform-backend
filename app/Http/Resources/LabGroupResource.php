<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cohort_id' => $this->cohort_id,
            'name' => $this->name,
            'student_count' => $this->whenCounted('students'),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Conditional
            'students' => CohortStudentResource::collection($this->whenLoaded('students')),
        ];
    }
}
