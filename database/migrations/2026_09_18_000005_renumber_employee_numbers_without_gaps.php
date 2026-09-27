<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $employees = DB::table('employees')
            ->get(['id', 'employee_no'])
            ->sortBy(fn ($employee) => [
                (int) preg_replace('/\D+/', '', $employee->employee_no),
                $employee->id,
            ])
            ->values();

        // Use temporary values first to preserve the unique employee_no constraint.
        foreach ($employees as $employee) {
            DB::table('employees')->where('id', $employee->id)->update([
                'employee_no' => 'TEMP-'.$employee->id,
            ]);
        }

        foreach ($employees as $index => $employee) {
            DB::table('employees')->where('id', $employee->id)->update([
                'employee_no' => 'COS-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
            ]);
        }
    }

    public function down(): void
    {
        // The consecutive COS employee number format is intentionally retained.
    }
};
