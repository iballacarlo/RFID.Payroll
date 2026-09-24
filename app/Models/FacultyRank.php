<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacultyRank extends Model
{
    protected $fillable = ['name', 'salary_grade', 'rate_type', 'rate_amount', 'monthly_salary', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}
