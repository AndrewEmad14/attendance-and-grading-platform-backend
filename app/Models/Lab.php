<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lab extends Model
{
  use HasFactory;

  protected $fillable = ['name', 'lab_group_id', 'course_id'];

  public function labGroup()
  {
    return $this->belongsTo(LabGroup::class);
  }

  public function course()
  {
    return $this->belongsTo(Course::class);
  }

  // Polymorphic engagements (lab sessions)
  public function engagements()
  {
    return $this->morphMany(Engagement::class, 'engageable');
  }
}
