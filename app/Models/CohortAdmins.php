<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CohortAdmins extends Model
{
    use HasFactory;

    protected $table = 'cohorts_admins';

    protected $fillable = ['cohort_id', 'staff_id'];

    public function cohort()
    {
        return $this->belongsTo(Cohort::class);
    }

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
