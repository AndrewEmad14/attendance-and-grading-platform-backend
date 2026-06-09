<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',
    'compensation_type',
    'hourly_rate',
  ];

  protected $casts = [
    'hourly_rate' => 'decimal:2',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function engagements()
  {
    return $this->hasMany(Engagement::class, 'staff_id');
  }
}
