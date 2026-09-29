<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'payroll_staff')
            ->where('name', 'Payroll Staff')
            ->update(['name' => 'Human Resources']);
    }

    public function down(): void
    {
        DB::table('users')
            ->where('role', 'payroll_staff')
            ->where('name', 'Human Resources')
            ->update(['name' => 'Payroll Staff']);
    }
};
