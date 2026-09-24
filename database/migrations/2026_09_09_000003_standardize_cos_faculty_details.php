<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('employees')->update([
            'position' => 'COS Faculty Member',
            'department' => 'Department of Computer Studies',
            'employment_type' => 'Contract of Service',
        ]);

        DB::table('faculty_schedules')->where('day_of_week', 0)->delete();
    }

    public function down(): void
    {
        // Existing standardized data is retained on rollback.
    }
};
