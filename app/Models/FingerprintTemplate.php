<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FingerprintTemplate extends Model
{
    protected $fillable = ['employee_id', 'fingerprint_code', 'finger_label', 'status', 'registered_at'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
