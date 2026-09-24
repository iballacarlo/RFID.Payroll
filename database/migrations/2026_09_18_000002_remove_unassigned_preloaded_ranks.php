<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The official ranks are selected one at a time from the admin dropdown.
        // Hide unassigned preloaded records without deleting any database data.
        DB::table('faculty_ranks')
            ->whereNotNull('salary_grade')
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('employees')
                    ->whereColumn('employees.faculty_rank_id', 'faculty_ranks.id');
            })
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Official choices are restored by the application only when an admin selects them.
    }
};
