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

  public function users()
  {
    return $this->belongsToMany(User::class, 'lab_group_users');
  }
}
