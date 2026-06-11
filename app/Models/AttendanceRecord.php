<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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


  public function student(): BelongsTo
  {
    return $this->belongsTo(StudentProfile::class, 'student_id');
  }

  public function engagement(): BelongsTo
  {
    return $this->belongsTo(Engagement::class, 'engagement_id');
  }

  public function isCheckedIn(): bool
  {
    return $this->arrived_at !== null && $this->left_at === null;
  }

  public function isComplete(): bool
  {
    return $this->arrived_at !== null && $this->left_at !== null;
  }
}
