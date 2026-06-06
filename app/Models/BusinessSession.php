<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BusinessSession extends Model
{
  use HasFactory;

  protected $fillable = ['name'];

  public function cohorts()
  {
    return $this->belongsToMany(Cohort::class, 'business_sessions_cohorts');
  }

  // Polymorphic engagements (business sessions)
  public function engagements()
  {
    return $this->morphMany(Engagement::class, 'engageable');
  }
}
