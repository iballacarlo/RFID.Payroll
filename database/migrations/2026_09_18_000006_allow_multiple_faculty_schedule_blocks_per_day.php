<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->index('employee_id', 'faculty_schedules_employee_id_index');
        });

        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'day_of_week']);
        });

        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->index(['employee_id', 'day_of_week'], 'faculty_schedules_employee_day_index');
        });
    }

    public function down(): void
    {
        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->dropIndex('faculty_schedules_employee_day_index');
        });

        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->unique(['employee_id', 'day_of_week']);
        });

        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->dropIndex('faculty_schedules_employee_id_index');
        });
    }
};
