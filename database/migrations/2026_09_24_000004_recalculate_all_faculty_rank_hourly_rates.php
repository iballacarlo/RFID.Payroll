<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ranks = DB::table('faculty_ranks')
            ->whereNotNull('monthly_salary')
            ->get(['id', 'monthly_salary']);

        foreach ($ranks as $rank) {
            $hourlyRate = round((float) $rank->monthly_salary / 22 / 8, 2);

            DB::table('faculty_ranks')->where('id', $rank->id)->update([
                'rate_type' => 'hourly',
                'rate_amount' => $hourlyRate,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            DB::table('employees')->where('faculty_rank_id', $rank->id)->update([
                'rate_type' => 'hourly',
                'rate_amount' => $hourlyRate,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // The corrected hourly rates are intentionally retained.
    }
};
