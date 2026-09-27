<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_ranks', function (Blueprint $table) {
            $table->string('salary_grade', 10)->nullable()->after('name');
            $table->decimal('monthly_salary', 12, 2)->nullable()->after('rate_amount');
        });

        $now = now();
        $ranks = [
            ['Instructor I', 'SG 12', 33947.00], ['Instructor III', 'SG 13', 36038.00],
            ['Assistant Professor I', 'SG 15', 42178.00], ['Assistant Professor II', 'SG 16', 45740.00],
            ['Assistant Professor III', 'SG 17', 49648.00], ['Assistant Professor IV', 'SG 18', 53922.00],
            ['Associate Professor I', 'SG 19', 59357.00], ['Associate Professor II', 'SG 20', 66130.00],
            ['Associate Professor III', 'SG 21', 73677.00], ['Associate Professor IV', 'SG 22', 82075.00],
            ['Associate Professor V', 'SG 23', 92569.00], ['Professor I', 'SG 24', 104253.00],
            ['Professor II', 'SG 25', 118621.00], ['Professor III', 'SG 26', 135027.00],
            ['Professor IV', 'SG 27', 153610.00], ['Professor V', 'SG 28', 174896.00],
            ['Professor VI', 'SG 30', 199348.00],
        ];

        foreach ($ranks as [$name, $salaryGrade, $monthlySalary]) {
            DB::table('faculty_ranks')->updateOrInsert(
                ['name' => $name],
                [
                    'salary_grade' => $salaryGrade,
                    'rate_type' => 'hourly',
                    // Initial reference rate: monthly salary divided by 22 workdays x 8 hours.
                    'rate_amount' => round($monthlySalary / 176, 2),
                    'monthly_salary' => $monthlySalary,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $replacementRank = DB::table('faculty_ranks')->where('name', 'Instructor I')->first();
        $legacyRankIds = DB::table('faculty_ranks')
            ->whereIn('name', ['Faculty 1', 'Faculty 2', 'Faculty 3', 'Faculty 4', 'Faculty 5'])
            ->pluck('id');

        if ($replacementRank && $legacyRankIds->isNotEmpty()) {
            DB::table('employees')->whereIn('faculty_rank_id', $legacyRankIds)->update([
                'faculty_rank_id' => $replacementRank->id,
                'rate_type' => 'hourly',
                'rate_amount' => $replacementRank->rate_amount,
                'updated_at' => $now,
            ]);

            DB::table('faculty_ranks')->whereIn('id', $legacyRankIds)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('faculty_ranks', function (Blueprint $table) {
            $table->dropColumn(['salary_grade', 'monthly_salary']);
        });
    }
};
