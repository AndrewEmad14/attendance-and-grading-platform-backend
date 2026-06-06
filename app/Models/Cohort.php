<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cohort extends Model
{
  use HasFactory;

  protected $fillable = ['number', 'track_id'];

  public function track()
  {
    return $this->belongsTo(Track::class);
  }

  public function labGroups()
  {
    return $this->hasMany(LabGroup::class);
  }

  public function engagements()
  {
    return $this->hasMany(Engagement::class);
  }

  public function courses()
  {
    return $this->hasMany(Course::class);
  }
}
