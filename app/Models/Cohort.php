<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'track_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function track()
    {
        return $this->belongsTo(Track::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function labGroups()
    {
        return $this->hasMany(LabGroup::class);
    }

    public function studentProfiles()
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function businessSessions()
    {
        return $this->belongsToMany(
            BusinessSession::class,
            'business_sessions_cohorts',
            'cohort_id',
            'business_session_id'
        );
    }

    public function trackAdmins()
    {
        return $this->belongsToMany(StaffProfile::class, 'cohorts_admins', 'cohort_id', 'staff_id');
    }

    public function engagements()
    {
        return $this->hasManyThrough(
            Engagement::class,
            Course::class,
            'cohort_id', // Foreign key on the courses table
            'engageable_id' // Foreign key on the engagements table
        )->where('engagements.engageable_type', Course::class);
    }

    public function announcements()
    {
        return $this->hasMany(Announcement::class);
    }
}
