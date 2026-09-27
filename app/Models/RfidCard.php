<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfidCard extends Model
{
    protected $fillable = ['employee_id', 'rfid_uid', 'status', 'registered_at'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
