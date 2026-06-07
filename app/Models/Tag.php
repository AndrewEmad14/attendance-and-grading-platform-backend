<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
  use HasFactory;

  protected $fillable = ['tag'];

  public function studentProfiles()
  {
    return $this->belongsToMany(StudentProfile::class, 'students_tags', 'tag_id', 'student_id');
  }
}
