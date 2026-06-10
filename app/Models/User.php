<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    const ROLE_BRANCH_MANAGER = 'branch_manager';
    const ROLE_TRACK_ADMIN = 'track_admin';
    const ROLE_INSTRUCTOR = 'instructor';
    const ROLE_STUDENT = 'student';

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'expires_at',
        'remember_token',
        'email_verified_at',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'expires_at'        => 'datetime',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function setPasswordHashAttribute($value): void
    {
        $this->attributes['password_hash'] =
            \Illuminate\Support\Facades\Hash::needsRehash($value)
            ? \Illuminate\Support\Facades\Hash::make($value)
            : $value;
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class, 'user_id');
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class, 'user_id');
    }

    public function taughtEngagements()
    {
        return $this->hasMany(Engagement::class, 'staff_id');
    }

    public function tags()
    {
        return $this->belongsToMany(
            Tag::class,
            'students_tags',
            'student_id',
            'tag_id'
        );
    }

    public function isBranchManager(): bool
    {
        return $this->role === self::ROLE_BRANCH_MANAGER;
    }

    public function isTrackAdmin(): bool
    {
        return $this->role === self::ROLE_TRACK_ADMIN;
    }

    public function isInstructor(): bool
    {
        return $this->role === self::ROLE_INSTRUCTOR;
    }

    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    public function isStaff(): bool
    {
        return in_array($this->role, [
            self::ROLE_BRANCH_MANAGER,
            self::ROLE_TRACK_ADMIN,
            self::ROLE_INSTRUCTOR,
        ]);
    }
}
