<?php

namespace App\Http\Resources;

use App\Models\BusinessSession;
use App\Models\Course;
use App\Models\Lab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngagementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $title = 'Unknown Session';
        $subtitle = null;

        if ($this->relationLoaded('engageable') && $this->engageable) {
            if ($this->engageable instanceof Course) {
                $title = $this->engageable->name;
                $subtitle = 'Lecture Session';
            } elseif ($this->engageable instanceof Lab) {
                $title = $this->engageable->name;
                $subtitle = $this->engageable->labGroup?->name ?? 'Lab Group';
            } elseif ($this->engageable instanceof BusinessSession) {
                $title = $this->engageable->name;
                $subtitle = 'Cross-Track Business Event';
            }
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'engageable_type' => $this->engageable_type,
            'engageable_id' => $this->engageable_id,
            'staff_id' => $this->staff_id,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'scheduled_hours' => $this->scheduled_hours,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // UI Aggregation Layer fields
            'display_title' => $title,
            'display_context' => $subtitle,
        ];
    }
}
