<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Engagement extends Model
{
  use HasFactory;

  protected $fillable = [
    'engageable_id',
    'engageable_type',
    'staff_id',
    'starts_at',
    'ends_at',
    'scheduled_hours',
  ];

  protected $casts = [
    'starts_at' => 'datetime',
    'ends_at' => 'datetime',
    'scheduled_hours' => 'integer',
  ];

  // Polymorphic relation
  public function engageable()
  {
    return $this->morphTo();
  }

  public function staff()
  {
    return $this->belongsTo(StaffProfile::class, 'staff_id');
  }

  public function attendanceRecords()
  {
    return $this->hasMany(AttendanceRecord::class);
  }

  public function billingRecord()
  {
    return $this->hasOne(BillingRecord::class);
  }
}
