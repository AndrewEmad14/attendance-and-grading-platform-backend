<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Engagement extends Model
{
    use HasFactory;

    protected $fillable = [
        'engageable_id',
        'engageable_type',
        'staff_id',
        'starts_at',
        'ends_at',
        'scheduled_hours',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'scheduled_hours' => 'integer',
    ];

    // Polymorphic relation
    public function engageable() // course,lab,meeting
    {
        return $this->morphTo();
    }

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function billingRecord()
    {
        return $this->hasOne(BillingRecord::class);
    }

    /**
     * Scope a query to only include engagements linked to a specific cohort.
     */
    public function scopeForCohort(Builder $query, int $cohortId): Builder
    {
        return $query->where(function ($q) use ($cohortId) {
            $q->whereHasMorph('engageable', [Course::class], function ($sub) use ($cohortId) {
                $sub->where('cohort_id', $cohortId);
            })->orWhereHasMorph('engageable', [Lab::class], function ($sub) use ($cohortId) {
                $sub->whereHas('labGroup', function ($lg) use ($cohortId) {
                    $lg->where('cohort_id', $cohortId);
                });
            })->orWhereHasMorph('engageable', [BusinessSession::class], function ($sub) use ($cohortId) {
                $sub->whereHas('cohorts', function ($c) use ($cohortId) {
                    $c->where('cohorts.id', $cohortId);
                });
            });
        });
    }
}
