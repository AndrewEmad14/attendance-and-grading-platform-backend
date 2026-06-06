<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Track extends Model
{
  use HasFactory;

  protected $fillable = ['name'];

  public function users()
  {
    return $this->hasMany(User::class);
  }

  public function cohorts()
  {
    return $this->hasMany(Cohort::class);
  }
}
