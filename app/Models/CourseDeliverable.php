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
    'weight',
  ];

  protected $casts = [
    'type' => 'string',
    'weight' => 'integer',
  ];

  public function course()
  {
    return $this->belongsTo(Course::class, 'course_id');
  }

  public function submissions()
  {
    return $this->hasMany(Submission::class, 'deliverable_id');
  }
}
