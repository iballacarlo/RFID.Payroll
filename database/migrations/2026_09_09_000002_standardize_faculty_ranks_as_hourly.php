<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $ranks = ['Faculty 1', 'Faculty 2', 'Faculty 3', 'Faculty 4', 'Faculty 5'];

        foreach ($ranks as $rank) {
            DB::table('faculty_ranks')->updateOrInsert(
                ['name' => $rank],
                ['rate_type' => 'hourly', 'rate_amount' => 0, 'is_active' => true, 'updated_at' => $now, 'created_at' => $now]
            );
        }

        DB::table('faculty_ranks')->update(['rate_type' => 'hourly']);
        DB::table('faculty_ranks')->whereNotIn('name', $ranks)->update(['is_active' => false]);
        DB::table('employees')->update(['rate_type' => 'hourly']);
    }

    public function down(): void
    {
        // Standardized rank records are intentionally retained on rollback.
    }
};
