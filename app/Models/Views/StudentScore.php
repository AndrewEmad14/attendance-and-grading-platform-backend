<?php

namespace App\Models\Views;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseDeliverable;

/**
 * Read-only model for v_student_scores.
 *
 * Each row is one student × one deliverable, with normalization
 * and late-penalty already applied (GRD-3, ENG-2).
 *
 * @property int         $student_id
 * @property int         $course_id
 * @property int         $deliverable_id
 * @property string      $type              lab | final_exam | ...
 * @property int         $course_weight
 * @property string|null $submitted_at
 * @property string|null $due_date
 * @property float       $component_score   normalized + penalty applied
 */
class StudentScore extends Model
{
  protected $table = 'v_student_scores';
  public    $timestamps = false;

  protected $casts = [
    'course_weight'   => 'integer',
    'component_score' => 'float',
    'submitted_at'    => 'datetime',
    'due_date'        => 'datetime',
  ];

  // ── Relationships ────────────────────────────────────────────────

  public function student(): BelongsTo
  {
    return $this->belongsTo(User::class, 'student_id');
  }

  public function course(): BelongsTo
  {
    return $this->belongsTo(Course::class, 'course_id');
  }

  public function deliverable(): BelongsTo
  {
    return $this->belongsTo(CourseDeliverable::class, 'deliverable_id');
  }

    // ── Common scopes ────────────────────────────────────────────────

  /** All scores for a single student. */
  public function scopeForStudent($query, int $studentId)
  {
    return $query->where('student_id', $studentId);
  }

  /** All scores for a single course. */
  public function scopeForCourse($query, int $courseId)
  {
    return $query->where('course_id', $courseId);
  }
}
