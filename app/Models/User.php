<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;

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
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'role' => 'string',
    ];

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function setPasswordHashAttribute($value): void
    {
        $this->attributes['password_hash'] =
            Hash::needsRehash($value)
            ? Hash::make($value)
            : $value;
    }

    public function studentProfile()
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
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
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token, $this->email));
    }
}
