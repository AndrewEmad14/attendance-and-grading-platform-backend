<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'compensation_type',
        'hourly_rate',
        'fixed_salary',
    ];

    protected $casts = [
        'hourly_rate' => 'integer',
        'fixed_salary' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function engagements()
    {
        return $this->hasMany(Engagement::class, 'staff_id');
    }

    public function managedCohorts()
    {
        return $this->belongsToMany(Cohort::class, 'cohorts_admins', 'staff_id', 'cohort_id');
    }

    public function overriddenSubmissions()
    {
        return $this->hasMany(Submission::class, 'overridden_by');
    }

    public function gradedSubmissions()
    {
        return $this->hasMany(Submission::class, 'graded_by');
    }

    public function reviewedExcuseRequests()
    {
        return $this->hasMany(ExcuseRequest::class, 'reviewed_by');
    }

    public function billingRecords()
    {
        return $this->hasMany(BillingRecord::class, 'staff_id');
    }
}
