<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
  use HasFactory;

  protected $fillable = ['cohort_id', 'name'];

  public function cohort()
  {
    return $this->belongsTo(Cohort::class);
  }

  public function deliverables()
  {
    return $this->hasMany(CourseDeliverable::class, 'course_id');
  }
}
