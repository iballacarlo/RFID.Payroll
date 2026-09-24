<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRecord extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'employee_id',
        'total_days_worked',
        'total_hours_worked',
        'gross_pay',
        'total_deductions',
        'total_adjustments',
        'net_pay',
        'status',
        'generated_at',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollPeriod()
    {
        return $this->belongsTo(PayrollPeriod::class);
    }
}
