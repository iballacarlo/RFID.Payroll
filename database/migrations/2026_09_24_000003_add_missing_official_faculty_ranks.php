<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $ranks = [
            ['Instructor II', 'SG 13', 36125.00],
            ['Instructor III', 'SG 14', 38764.00],
            ['Professor VI', 'SG 29', 194846.00],
            ['College/University Professor', 'SG 30', 210718.00],
        ];

        foreach ($ranks as [$name, $salaryGrade, $monthlySalary]) {
            DB::table('faculty_ranks')->updateOrInsert(
                ['name' => $name],
                [
                    'salary_grade' => $salaryGrade,
                    'rate_type' => 'hourly',
                    'rate_amount' => round($monthlySalary / 176, 2),
                    'monthly_salary' => $monthlySalary,
                    'is_active' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('faculty_ranks')
            ->whereIn('name', ['Instructor II', 'College/University Professor'])
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('employees')
                    ->whereColumn('employees.faculty_rank_id', 'faculty_ranks.id');
            })
            ->delete();
    }
};
