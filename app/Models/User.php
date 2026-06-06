<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Track;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'expires_at',
        'track_id',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'role' => 'string', // enum
    ];

    // Mutator for password hashing
    public function setPasswordAttribute($value)
    {
        $this->attributes['password_hash'] = bcrypt($value);
    }

    // Relationships
    public function track()
    {
        return $this->belongsTo(Track::class);
    }

    public function labGroups()
    {
        return $this->belongsToMany(LabGroup::class, 'lab_group_users');
    }

    public function taughtEngagements()
    {
        return $this->hasMany(Engagement::class, 'instructor_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'student_id');
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class, 'staff_id');
    }

    public function attendanceLedger()
    {
        return $this->hasOne(AttendanceLedger::class, 'student_id');
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class, 'student_id');
    }
}
