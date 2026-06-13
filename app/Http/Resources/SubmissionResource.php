<?php

namespace App\Http\Resources;

use App\Services\GradingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubmissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $gradingService = new GradingService;

        return [
            'id' => $this->id,
            'deliverable_id' => $this->deliverable_id,
            'student_id' => $this->student_id,
            'submission_type' => $this->submission_type,
            'submission_path' => $this->submission_path,
            'submitted_at' => $this->created_at,          // ← added (slice tracker timestamp)
            'status' => $this->deriveStatus(),            // ← added (completed | late)
            'raw_score' => $this->raw_score,
            'override_score' => $this->override_score,
            'override_note' => $this->override_note,
            'effective_raw_score' => $this->effectiveRawScore(),
            'normalized_score' => $this->whenLoaded(
                'deliverable',
                fn () => $gradingService->computeNormalizedScore($this->resource)
            ),
            'is_overridden' => ! is_null($this->override_score),
            'graded_by' => $this->graded_by ? [
                'id' => $this->graded_by,
                'name' => $this->whenLoaded('gradedBy', fn () => $this->gradedBy?->user?->name),
            ] : null,
            'overridden_by' => $this->overridden_by ? [
                'id' => $this->overridden_by,
                'name' => $this->whenLoaded('overriddenBy', fn () => $this->overriddenBy?->user?->name),
            ] : null,
            'overridden_at' => $this->overridden_at,
            'student' => $this->whenLoaded('student', fn () => [
                'id' => $this->student_id,
                'name' => $this->student?->user?->name,
                'lab_group' => $this->student?->relationLoaded('labGroup') && $this->student?->labGroup ? [
                    'id' => $this->student->labGroup->id,
                    'name' => $this->student->labGroup->name,
                ] : null,
            ]),
        ];
    }

    /**
     * Submission status (your slice). completed | late.
     * 'missing' never appears here — a missing student has no submission row
     * and is served by the /missing endpoint via CohortStudentResource.
     */
    private function deriveStatus(): ?string
    {
        if (! $this->relationLoaded('deliverable') || $this->deliverable === null) {
            return null; // due_date unknown without the deliverable; don't guess
        }

        $due = $this->deliverable->due_date;
        if ($due === null) {
            return 'completed';
        }

        return $this->created_at->gt($due) ? 'late' : 'completed';
    }
}
