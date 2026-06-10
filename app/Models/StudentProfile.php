<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
}
