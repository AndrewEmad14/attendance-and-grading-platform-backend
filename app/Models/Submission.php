<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Submission extends Model
{
  use HasFactory;

  protected $fillable = [
    'deliverable_id', //laravel proj
    'student_id',
    'submission_type', //github_link
    'submission_path', //the link
    'raw_score',
    'override_score',
    'override_note',
    'graded_by',
    'overriden_by',
    'overriden_at'
  ];

  protected $casts = [
    'raw_score' => 'float', //decimal ret. str
    'override_score' => 'float',
    'overriden_at' => 'datetime'
  ];

  public function deliverable()
  {
    return $this->belongsTo(CourseDeliverable::class, 'deliverable_id');
  }

  public function student()
  {
    return $this->belongsTo(StudentProfile::class, 'student_id');
  }

  public function effectiveRawScore(): ?float // if there is overriden score take it else the raw one
  {
    return $this->override_score ?? $this->raw_score;
  }

  public function gradedBy(): BelongsTo // user who graded submission (restriction on who is allowed in gradepolicy)
  {
    return $this->belongsTo(User::class, 'graded_by');
  }

  public function overriddenBy(): BelongsTo // user overriden grade (track admin in policy)
  {
    return $this->belongsTo(User::class, 'overriden_by');
  }
}
