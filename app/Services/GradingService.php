<?php

namespace App\Services;

use App\Models\Submission;

class GradingService // grading logic
{
    public function computeNormalizedScore(Submission $submission): ?float // normalized score on fly
    {
        $raw = $submission->effectiveRawScore(); // override_score ?? raw_score

        if ($raw === null) {
            return null;
        }

        $deliverable = $submission->deliverable;

        if (!$deliverable || $deliverable->max_score == 0) {
            return 0;
        }

        return round(($raw / $deliverable->max_score) * $deliverable->course_weight, 2);
    }

    public function applyGrade(Submission $submission, float $rawScore, int $gradedBy): Submission
    {
        $submission->raw_score = $rawScore;
        $submission->graded_by = $gradedBy;
        $submission->save();

        return $submission;
    }

    public function applyOverride(Submission $submission, float $newScore, string $note, int $overriddenBy): Submission
    { //save overidden data
        $submission->override_score  = $newScore;
        $submission->override_note   = $note;
        $submission->overridden_by   = $overriddenBy;
        $submission->overridden_at   = now();
        $submission->save();

        return $submission;
    }
}
