<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class ExcuseRequest extends Model
{
  use HasFactory;
  const STATUS_PENDING  = 'pending';
  const STATUS_APPROVED = 'approved';
  const STATUS_REJECTED = 'rejected';

  protected $fillable = [
    'attendance_id',
    'reason',
    'attachment_path',
    'status',
    'reviewed_by',
    'reviewed_at',
  ];

  protected $casts = [
    'reviewed_at' => 'datetime',
  ];

  public function attendanceRecord()
  {
    return $this->belongsTo(AttendanceRecord::class, 'attendance_id');
  }

  public function reviewer()
  {
    return $this->belongsTo(StaffProfile::class, 'reviewed_by');
  }
  public function isPending(): bool
  {
    return $this->status === self::STATUS_PENDING;
  }

  public function isApproved(): bool
  {
    return $this->status === self::STATUS_APPROVED;
  }

  public function isRejected(): bool
  {
    return $this->status === self::STATUS_REJECTED;
  }

  public function student(): HasOneThrough
  {
    return $this->hasOneThrough(
      StudentProfile::class,
      AttendanceRecord::class,
      'id', // attendance_records.id matched against excuse_requests.attendance_id
      'id', // student_profiles.id matched against attendance_records.student_id
      'attendance_id', // excuse_requests.attendance_id
      'student_id' // attendance_records.student_id
    );
  }
}
