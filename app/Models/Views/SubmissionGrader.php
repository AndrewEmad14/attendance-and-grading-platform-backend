<?php

namespace App\Models\Views;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Submission;

/**
 * Read-only model for v_submission_grader.
 *
 * Answers: "given a submission, which instructor is assigned to grade it?"
 * Resolves: submission → deliverable (type=lab) → student_profiles →
 *           lab_group → labs → engagements (instructor_id)
 *
 * Primary use: authorization. Before a grade is saved, check that
 * the authenticated instructor appears in this view for that submission.
 *
 * @property int $submission_id
 * @property int $student_id
 * @property int $deliverable_id
 * @property int $lab_id
 * @property int $lab_group_id
 * @property int $engagement_id
 * @property int $grader_id       the instructor authorized to grade this submission
 */
class SubmissionGrader extends Model
{
  protected $table = 'v_submission_grader';
  public    $timestamps = false;

  // ── Relationships ────────────────────────────────────────────────

  public function submission(): BelongsTo
  {
    return $this->belongsTo(Submission::class, 'submission_id');
  }

  public function grader(): BelongsTo
  {
    return $this->belongsTo(User::class, 'grader_id');
  }

    // ── Authorization helpers ────────────────────────────────────────

  /**
   * The instructor authorized to grade a specific submission.
   * Returns null if the submission has no assigned instructor
   * (e.g. not a lab deliverable).
   *
   * Example:
   *   $graderId = SubmissionGrader::graderFor($submission->id)?->grader_id;
   */
  public static function graderFor(int $submissionId): ?self
  {
    return static::where('submission_id', $submissionId)->first();
  }

  /**
   * Whether a given user is authorized to grade a given submission.
   * Combine with InstructorLabSubmission::canGrade() in your Policy
   * — they query the same relationship from opposite directions.
   *
   * Example in SubmissionPolicy:
   *   public function grade(User $user, Submission $submission): bool
   *   {
   *       return SubmissionGrader::authorizedFor($submission->id, $user->id);
   *   }
   */
  public static function authorizedFor(int $submissionId, int $userId): bool
  {
    return static::where('submission_id', $submissionId)
      ->where('grader_id', $userId)
      ->exists();
  }
}
