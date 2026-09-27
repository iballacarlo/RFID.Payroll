<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_periods')->where('period_name', 'Sample Payroll Period')->delete();
    }

    public function down(): void
    {
        // Deleted sample data is intentionally not restored.
    }
};
