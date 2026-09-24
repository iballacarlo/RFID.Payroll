<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $employees = DB::table('employees')
            ->orderByRaw('CAST(employee_no AS UNSIGNED)')
            ->orderBy('id')
            ->get(['id']);

        // Temporarily remove unique values before assigning the new sequence.
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
        // The standardized employee number format is intentionally retained.
    }
};
