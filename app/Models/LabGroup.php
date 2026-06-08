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
    return $this->hasMany(StudentProfile::class);
  }

  public function labs()
  {
    return $this->hasMany(Lab::class);
  }
}
