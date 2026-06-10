<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Course;
use App\Models\Lab;
use App\Models\BusinessSession;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Engagement extends Model
{
  use HasFactory;
  const TYPE_COURSE = 'App\\Models\\Course';
  const TYPE_LAB = 'App\\Models\\Lab';
  const TYPE_BUSINESS_SESSION = 'App\\Models\\BusinessSession';

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
  public function engageable(): MorphTo // course,lab,meeting
  {
    return $this->morphTo();
  }

  public function staff(): BelongsTo
  {
    return $this->belongsTo(StaffProfile::class, 'staff_id');
  }

  public function attendanceRecords(): HasMany
  {
    return $this->hasMany(AttendanceRecord::class);
  }

  public function billingRecord(): HasMany
  {
    return $this->hasMany(BillingRecord::class, 'engagement_id');
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

  public function isLecture(): bool
  {
    return $this->engageable_type === self::TYPE_COURSE;
  }

  public function isLab(): bool
  {
    return $this->engageable_type === self::TYPE_LAB;
  }

  public function isBusinessSession(): bool
  {
    return $this->engageable_type === self::TYPE_BUSINESS_SESSION;
  }

  public function type(): string
  {
    return match ($this->engageable_type) {
      self::TYPE_COURSE => 'lecture',
      self::TYPE_LAB => 'lab',
      self::TYPE_BUSINESS_SESSION => 'business_session',
    };
  }
}
