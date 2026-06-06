<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
  use HasFactory;

  protected $fillable = [
    'deliverable_id',
    'student_id',
    'submission_type',
    'submission_path',
    'raw_score',
    'override_score',
    'override_note',
  ];

  protected $casts = [
    'raw_score'      => 'decimal:2',
    'override_score' => 'decimal:2',
  ];

  public function deliverable()
  {
    return $this->belongsTo(CourseDeliverable::class);
  }

  public function student()
  {
    return $this->belongsTo(StudentProfile::class, 'student_id');
  }
}
