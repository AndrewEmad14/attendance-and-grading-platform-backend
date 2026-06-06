<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
  use HasFactory;

  protected $fillable = [
    'staff_id',
    'compensation_type',
    'hourly_rate',
  ];

  protected $casts = [
    'compensation_type' => 'string',
    'hourly_rate' => 'decimal:2',
  ];

  public function staff()
  {
    return $this->belongsTo(User::class, 'staff_id');
  }
}
