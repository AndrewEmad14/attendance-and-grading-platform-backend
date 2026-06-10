<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExcuseRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_id',
        'reason',
        'attachment_path',
        'status',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function attendanceRecord()
    {
        return $this->belongsTo(AttendanceRecord::class, 'attendance_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(StaffProfile::class, 'reviewed_by');
    }
}
