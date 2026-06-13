<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;

class Engagement extends Model
{
    use HasFactory;

    const TYPE_COURSE = Course::class;

    const TYPE_LAB = Lab::class;

    const TYPE_BUSINESS_SESSION = BusinessSession::class;

    protected $fillable = [
        'engageable_id',
        'engageable_type',
        'staff_id',
        'starts_at',
        'ends_at',
        'scheduled_hours',
        'absences_processed_at',
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

    // Get array of student IDs  expected to attend this engagement
    // Used in single engagement check, NOT in loops/array of engagements
    protected function expectedStudentIds(): Attribute
    {
        return Attribute::make(
            get: function (): array {
                return match ($this->engageable_type) {
                    self::TYPE_COURSE => StudentProfile::where('cohort_id', $this->engageable->cohort_id)
                        ->pluck('id')
                        ->toArray(),

                    self::TYPE_LAB => StudentProfile::where('lab_group_id', $this->engageable->lab_group_id)
                        ->pluck('id')
                        ->toArray(),

                    self::TYPE_BUSINESS_SESSION => StudentProfile::whereHas(
                        'cohort.businessSessions',
                        fn ($q) => $q->where('business_sessions.id', $this->engageable_id)
                    )->pluck('id')->toArray(),

                    default => [],
                };
            }
        );
    }

    // Get a list of <engagement_id, student_id[]> for a list of engagements you pass in
    public static function expectedStudentIdsForMany(Collection $engagements): array
    {
        if ($engagements->isEmpty()) {
            return [];
        }
        $engagements->loadMissing('engageable');
        $expected = [];

        $courses = $engagements->where('engageable_type', self::TYPE_COURSE);
        if ($courses->isNotEmpty()) {
            $byCohort = StudentProfile::whereIn('cohort_id', $courses->pluck('engageable.cohort_id')->unique())
                ->get(['id', 'cohort_id'])
                ->groupBy('cohort_id')
                ->map(fn ($s) => $s->pluck('id')->toArray());

            foreach ($courses as $e) {
                $expected[$e->id] = $byCohort->get($e->engageable->cohort_id, []);
            }
        }

        $labs = $engagements->where('engageable_type', self::TYPE_LAB);
        if ($labs->isNotEmpty()) {
            $byGroup = StudentProfile::whereIn('lab_group_id', $labs->pluck('engageable.lab_group_id')->unique())
                ->get(['id', 'lab_group_id'])
                ->groupBy('lab_group_id')
                ->map(fn ($s) => $s->pluck('id')->toArray());

            foreach ($labs as $e) {
                $expected[$e->id] = $byGroup->get($e->engageable->lab_group_id, []);
            }
        }

        $sessions = $engagements->where('engageable_type', self::TYPE_BUSINESS_SESSION);
        if ($sessions->isNotEmpty()) {
            $sessionsWithCohorts = BusinessSession::with('cohorts')
                ->whereIn('id', $sessions->pluck('engageable_id')->unique())
                ->get()
                ->keyBy('id');

            $allCohortIds = $sessionsWithCohorts->flatMap(fn ($s) => $s->cohorts->pluck('id'))->unique();

            $byCohort = StudentProfile::whereIn('cohort_id', $allCohortIds)
                ->get(['id', 'cohort_id'])
                ->groupBy('cohort_id')
                ->map(fn ($s) => $s->pluck('id')->toArray());

            foreach ($sessions as $e) {
                $cohortIds = $sessionsWithCohorts->get($e->engageable_id)?->cohorts->pluck('id') ?? collect();
                $expected[$e->id] = $cohortIds->flatMap(fn ($cid) => $byCohort->get($cid, []))->unique()->values()->toArray();
            }
        }

        return $expected;
    }

    // Given engagements, get a collection of <engagement_id, [student_id => array_index]>
    // Flip is used to optimize for O(1) lookup speed in usage
    // ex: isset($attendedStudents[$studentId]) directly checks if $studentId attended
    public static function attendedStudentIdsForMany(Collection $engagements): Collection
    {
        return AttendanceRecord::whereIn('engagement_id', $engagements->pluck('id'))
            ->whereNotNull('arrived_at')
            ->get(['engagement_id', 'student_id'])
            ->groupBy('engagement_id')
            ->map(fn ($records) => $records->pluck('student_id')->flip());
    }

    public static function excuseRequestsForMany(Collection $engagements): Collection
    {
        return ExcuseRequest::whereIn('engagement_id', $engagements->pluck('id'))
            ->get(['engagement_id', 'student_id', 'status'])
            ->groupBy('engagement_id')
            ->map(fn ($requests) => $requests->keyBy('student_id'));
    }

    // Scope a query to only include engagements linked to a specific cohort
    public function scopeForCohort(Builder $query, $cohortIds): Builder
    {
        $ids = is_array($cohortIds) ? $cohortIds : [$cohortIds];

        return $query->where(function ($q) use ($ids) {
            $q->whereHasMorph('engageable', [Course::class], function ($sub) use ($ids) {
                $sub->whereIn('cohort_id', $ids);
            })->orWhereHasMorph('engageable', [Lab::class], function ($sub) use ($ids) {
                $sub->whereHas('labGroup', function ($lg) use ($ids) {
                    $lg->whereIn('cohort_id', $ids);
                });
            })->orWhereHasMorph('engageable', [BusinessSession::class], function ($sub) use ($ids) {
                $sub->whereHas('cohorts', function ($c) use ($ids) {
                    $c->whereIn('cohorts.id', $ids);
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

    public function getEngagementTypeLabelAttribute(): string
    {
        return match ($this->engageable_type) {
            Course::class => 'lecture',
            Lab::class => 'lab',
            BusinessSession::class => 'business_session'
        };
    }
}
