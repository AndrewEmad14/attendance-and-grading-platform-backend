<?php

namespace App\Models\Views;

use App\Models\Cohort;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only model for v_at_risk_students.
 *
 * Only contains students who are currently at-risk (ANL-1):
 *   - attendance_balance < 150, OR
 *   - any course total < 60
 *
 * Used in Track Admin, Instructor, and Branch Manager dashboards.
 *
 * @property int $student_id
 * @property int $cohort_id
 * @property bool $at_risk_attendance
 * @property bool $at_risk_grade
 */
class AtRiskStudent extends Model
{
    protected $table = 'v_at_risk_students';

    public $timestamps = false;

    protected $casts = [
        'at_risk_attendance' => 'boolean',
        'at_risk_grade' => 'boolean',
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

    /** All at-risk students in a cohort. */
    public function scopeForCohort($query, int $cohortId)
    {
        return $query->where('cohort_id', $cohortId);
    }

    /** Only students at risk due to low attendance. */
    public function scopeAttendanceRisk($query)
    {
        return $query->where('at_risk_attendance', true);
    }

    /** Only students at risk due to a failing course grade. */
    public function scopeGradeRisk($query)
    {
        return $query->where('at_risk_grade', true);
    }
}
