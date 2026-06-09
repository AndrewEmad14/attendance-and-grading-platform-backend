<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array // what is sent to client
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'cohort_id'   => $this->cohort_id,
            'deliverables' => $this->whenLoaded('deliverables', function () { //no N+1 on with('delivarables')
                return $this->deliverables->map(fn($d) => [
                    'id'            => $d->id,
                    'name'          => $d->name,
                    'type'          => $d->type,
                    'max_score'     => $d->max_score,
                    'course_weight' => $d->course_weight,
                    'due_date'      => $d->due_date,
                ]);
            }),
        ];    
    }
}
