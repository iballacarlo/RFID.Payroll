<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollPeriod extends Model
{
    protected $fillable = ['period_name', 'start_date', 'end_date', 'pay_date', 'status'];

    public function records()
    {
        return $this->hasMany(PayrollRecord::class);
    }
}
