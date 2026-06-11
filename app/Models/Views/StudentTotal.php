<?php

namespace App\Models\Views;

use App\Models\Cohort;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only model for v_student_totals.
 *
 * One row per student. Exposes the grand total used everywhere:
 * student portal, Track Admin grade table, Branch Manager rollup.
 *
 * Grand total = attendance_balance + course_score  (Section 6.1)
 *
 * @property int $student_id
 * @property int $cohort_id
 * @property int $attendance_balance
 * @property float $course_score
 * @property float $grand_total
 */
class StudentTotal extends Model
{
    protected $table = 'v_student_totals';

    public $timestamps = false;

    protected $casts = [
        'attendance_balance' => 'integer',
        'course_score' => 'float',
        'grand_total' => 'float',
    ];

    // ── Relationships ────────────────────────────────────────────────

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    // ── Common scopes ────────────────────────────────────────────────

    /** Totals for every student in a cohort — used by Track Admin and Branch Manager. */
    public function scopeForCohort($query, int $cohortId)
    {
        return $query->where('cohort_id', $cohortId);
    }

    /** Single student's total — used by the student portal. */
    public function scopeForStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}
