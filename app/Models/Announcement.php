<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'staff_id',
        'cohort_id',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class);
    }

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }

    public function scopeForStudent($query, StudentProfile $student)
    {
        return $query->where(function ($q) use ($student) {
            $q->where('cohort_id', null)
                ->orWhere('cohort_id', $student->cohort_id);
        })->whereNotNull('published_at');
    }
}
