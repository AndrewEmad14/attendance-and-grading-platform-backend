<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class StudentProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cohort_id',
        'lab_group_id',
        'notes',
        'attendance_balance',
    ];

    protected $casts = [
        'attendance_balance' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'students_tags', 'student_id', 'tag_id');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'student_id');
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class, 'cohort_id');
    }

    public function labGroup(): BelongsTo
    {
        return $this->belongsTo(LabGroup::class, 'lab_group_id');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class, 'student_id');
    }

    public function expectedToAttend(Engagement $engagement): bool
    {
        return match ($engagement->engageable_type) {
            Engagement::TYPE_COURSE => $this->cohort_id === $engagement->engageable?->cohort_id,
            Engagement::TYPE_LAB => $this->lab_group_id === $engagement->engageable?->lab_group_id,
            Engagement::TYPE_BUSINESS_SESSION => $engagement->engageable?->cohorts
                ?->contains('id', $this->cohort_id) ?? false,
            default => false,
        };
    }

    // Base query builder for all engagements a student is expected to attend
    public function expectedEngagementsQuery(): Builder
    {
        return Engagement::with('engageable')
            ->where(function ($query) {
                $query->whereHasMorph(
                    'engageable',
                    [Course::class],
                    fn($q) => $q->where('cohort_id', $this->cohort_id)
                )->orWhereHasMorph(
                    'engageable',
                    [Lab::class],
                    fn($q) => $q->where('lab_group_id', $this->lab_group_id)
                )->orWhereHasMorph(
                    'engageable',
                    [BusinessSession::class],
                    fn($q) => $q->whereHas(
                        'cohorts',
                        fn($c) => $c->where('cohorts.id', $this->cohort_id)
                    )
                );
            });
    }
}
