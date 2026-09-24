<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('faculty_ranks')
            ->whereNotNull('salary_grade')
            ->update(['is_active' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // The official rank list remains fixed.
    }
};
