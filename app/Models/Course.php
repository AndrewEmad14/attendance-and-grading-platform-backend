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
        return $this->hasMany(CourseDeliverable::class);
    }

    public function labs()
    {
        return $this->hasMany(Lab::class);
    }

    public function engagements()
    {
        return $this->morphMany(Engagement::class, 'engageable');
    }
}
