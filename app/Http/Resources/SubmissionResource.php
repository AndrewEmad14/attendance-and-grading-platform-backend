<?php

namespace App\Http\Resources;

use App\Models\StaffProfile;
use App\Services\GradingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $gradingService = new GradingService;

        return [
            'id' => $this->id,
            'deliverable_id' => $this->deliverable_id,
            'student_id' => $this->student_id,
            'submission_type' => $this->submission_type,
            'submission_path' => $this->submission_path,
            'raw_score' => $this->raw_score,
            'override_score' => $this->override_score,
            'override_note' => $this->override_note,
            'effective_raw_score' => $this->effectiveRawScore(), // override_score ?? raw_score
            'normalized_score' => $this->whenLoaded(
                'deliverable', // only compute when deliverable is loaded
                fn () => $gradingService->computeNormalizedScore($this->resource)
            ),
            'is_overridden' => ! is_null($this->override_score), // true when override exists
            'graded_by' => $this->graded_by ? [
                'id' => $this->graded_by,
                'name' => StaffProfile::find($this->graded_by)?->user->name,
            ] : null,
            'overridden_by' => $this->overridden_by ? [
                'id' => $this->overridden_by,
                'name' => StaffProfile::find($this->overridden_by)?->user->name,
            ] : null,
            'overridden_at' => $this->overridden_at,
        ];
    }
}
