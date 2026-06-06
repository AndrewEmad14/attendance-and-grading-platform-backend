<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Engagement extends Model
{
  use HasFactory;

  protected $fillable = [
    'cohort_id',
    'instructor_id',
    'type',
    'starts_at',
    'ends_at',
    'scheduled_hours',
  ];

  protected $casts = [
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
    'scheduled_hours' => 'decimal:2',
    'type' => 'string', // enum
  ];

  public function cohort()
  {
    return $this->belongsTo(Cohort::class);
  }

  public function instructor()
  {
    return $this->belongsTo(User::class, 'instructor_id');
  }

  public function attendanceRecords()
  {
    return $this->hasMany(AttendanceRecord::class);
  }
}
