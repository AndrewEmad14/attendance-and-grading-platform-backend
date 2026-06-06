<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseDeliverable extends Model
{
  use HasFactory;

  protected $table = 'courses_deliverables';

  protected $fillable = [
    'course_id',
    'type',
    'name',
    'max_score',
    'course_weight',
    'due_date',
  ];

  protected $casts = [
    'due_date'  => 'datetime',
    'max_score' => 'integer',
    'course_weight' => 'integer',
  ];

  public function course()
  {
    return $this->belongsTo(Course::class);
  }

  public function submissions()
  {
    return $this->hasMany(Submission::class, 'deliverable_id');
  }
}
