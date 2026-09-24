<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceLog extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_date',
        'time_in',
        'time_out',
        'method_in',
        'method_out',
        'late_minutes',
        'undertime_minutes',
        'total_hours',
        'status',
        'remarks',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
