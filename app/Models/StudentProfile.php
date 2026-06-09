<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function cohort()
  {
    return $this->belongsTo(Cohort::class);
  }

  public function tags()
  {
    return $this->belongsToMany(Tag::class, 'students_tags', 'student_id', 'tag_id');
  }

  public function labGroup()
  {
    return $this->belongsTo(LabGroup::class);
  }

  public function attendanceRecords()
  {
    return $this->hasMany(AttendanceRecord::class, 'student_id');
  }

  public function submissions()
  {
    return $this->hasMany(Submission::class, 'student_id');
  }
}
