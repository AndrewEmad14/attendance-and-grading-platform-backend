<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'engagement_id',
        'student_id',
        'arrived_at',
        'left_at',
    ];

    protected $casts = [
        'arrived_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    public function engagement()
    {
        return $this->belongsTo(Engagement::class);
    }

    public function student()
    {
        return $this->belongsTo(StudentProfile::class, 'student_id');
    }

    public function excuseRequest()
    {
        return $this->hasOne(ExcuseRequest::class, 'attendance_id');
    }
}
