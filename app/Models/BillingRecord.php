<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BillingRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'engagement_id',
        'staff_id',
        'delivered_hours',
        'total_amount',
        'forwarded_at'
    ];

    public function engagement()
    {
        return $this->belongsTo(Engagement::class);
    }

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }
}
