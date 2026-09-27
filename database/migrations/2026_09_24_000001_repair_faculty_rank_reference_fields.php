<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ranks = [
            'Instructor I' => ['SG 12', 33947.00], 'Instructor III' => ['SG 13', 36038.00],
            'Assistant Professor I' => ['SG 15', 42178.00], 'Assistant Professor II' => ['SG 16', 45740.00],
            'Assistant Professor III' => ['SG 17', 49648.00], 'Assistant Professor IV' => ['SG 18', 53922.00],
            'Associate Professor I' => ['SG 19', 59357.00], 'Associate Professor II' => ['SG 20', 66130.00],
            'Associate Professor III' => ['SG 21', 73677.00], 'Associate Professor IV' => ['SG 22', 82075.00],
            'Associate Professor V' => ['SG 23', 92569.00], 'Professor I' => ['SG 24', 104253.00],
            'Professor II' => ['SG 25', 118621.00], 'Professor III' => ['SG 26', 135027.00],
            'Professor IV' => ['SG 27', 153610.00], 'Professor V' => ['SG 28', 174896.00],
            'Professor VI' => ['SG 30', 199348.00],
        ];

        foreach ($ranks as $name => [$salaryGrade, $monthlySalary]) {
            DB::table('faculty_ranks')->where('name', $name)->update([
                'salary_grade' => $salaryGrade,
                'monthly_salary' => $monthlySalary,
            ]);
        }
    }

    public function down(): void
    {
        // Existing reference fields are retained when rolling back.
    }
};
