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

  public function labGroups()
  {
    return $this->belongsToMany(LabGroup::class, 'lab_group_users', 'student_id', 'lab_group_id');
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
