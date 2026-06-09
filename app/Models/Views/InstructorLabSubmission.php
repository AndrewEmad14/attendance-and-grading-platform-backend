<?php

namespace App\Models\Views;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Submission;
use App\Models\CourseDeliverable;
use Illuminate\Support\Facades\DB;

/**
 * Read-only model for v_instructor_lab_submissions.
 *
 * Answers: "given an instructor, which lab submissions are theirs to grade?"
 * Resolves: engagement → lab → lab_group → student_profiles → submissions
 *
 * @property int         $staff_id
 * @property int         $engagement_id
 * @property int         $lab_id
 * @property int         $lab_group_id
 * @property int         $student_id
 * @property int         $submission_id
 * @property int         $deliverable_id
 * @property float|null  $raw_score
 * @property int|null    $graded_by
 * @property string|null $graded_at
 * @property int|null    $overridden_by
 * @property string|null $created_at
 */
class InstructorLabSubmission extends Model
{
  protected $table = 'v_instructor_lab_submissions';
  public    $timestamps = false;

  protected $casts = [
    'graded_at'    => 'datetime',
    'created_at' => 'datetime',
  ];

  // ── Relationships ────────────────────────────────────────────────

  public function instructor(): BelongsTo
  {
    return $this->belongsTo(User::class, 'staff_id');
  }

  public function student(): BelongsTo
  {
    return $this->belongsTo(User::class, 'student_id');
  }

  public function submission(): BelongsTo
  {
    return $this->belongsTo(Submission::class, 'submission_id');
  }

  public function deliverable(): BelongsTo
  {
    return $this->belongsTo(CourseDeliverable::class, 'deliverable_id');
  }

    // ── Common scopes ────────────────────────────────────────────────

  /** All lab submissions an instructor must grade. */
  public function scopeForInstructor($query, int $instructorId)
  {
    return $query->where('staff_id', $instructorId);
  }

  /** Ungraded submissions only. */
  public function scopeUngraded($query)
  {
    return $query->whereNull('graded_by');
  }

  /** Submissions that were overridden by a Track Admin. */
  public function scopeOverridden($query)
  {
    return $query->whereNotNull('overridden_by');
  }

    // ── Authorization helper ─────────────────────────────────────────

  /**
   * Returns true if the given instructor is allowed to grade
   * the given submission. Used in policy checks before saving a grade.
   *
   * Example:
   *   abort_unless(InstructorLabSubmission::canGrade($user->id, $submission->id), 403);
   */
  public static function canGrade(int $instructorId, int $submissionId): bool
  {
    return static::where('staff_id', $instructorId)
      ->where('submission_id', $submissionId)
      ->exists();
  }

  // ── Course-level helpers ─────────────────────────────────────────

  /**
   * All instructor IDs delivering labs for a given course.
   *
   * Cannot be a SQL view because it requires a runtime course_id parameter.
   * Uses a join instead of a subquery to avoid the "more than one row"
   * error that = would cause when a course has multiple labs.
   *
   * Example:
   *   $ids = InstructorLabSubmission::instructorIdsForCourse($courseId);
   *   $instructors = User::whereIn('id', $ids)->get();
   */
  public static function instructorIdsForCourse(int $courseId)
  {
    return DB::table('engagements as e')
      ->join('labs as l', function ($join) {
        $join->on('l.id', '=', 'e.engageable_id')
          ->where('e.engageable_type', '=', 'lab');
      })
      ->where('l.course_id', $courseId)
      ->distinct()
      ->pluck('e.staff_id');
  }
}
