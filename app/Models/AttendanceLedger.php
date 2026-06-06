<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceLedger extends Model
{
  use HasFactory;

  protected $fillable = [
    'student_id',
    'balance',
  ];

  protected $casts = [
    'balance' => 'integer',
  ];

  public function student()
  {
    return $this->belongsTo(User::class, 'student_id');
  }
}
