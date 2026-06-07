<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabGroup extends Model
{
  use HasFactory;

  protected $fillable = ['name', 'cohort_id'];

  public function cohort()
  {
    return $this->belongsTo(Cohort::class);
  }

  public function students()
  {
    return $this->belongsToMany(StudentProfile::class, 'lab_group_users', 'lab_group_id', 'student_id');
  }

  public function labs()
  {
    return $this->hasMany(Lab::class);
  }
}
