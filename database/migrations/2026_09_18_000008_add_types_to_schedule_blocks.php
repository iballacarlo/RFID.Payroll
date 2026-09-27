<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->string('schedule_type', 24)->default('class')->after('day_of_week');
        });

        Schema::table('faculty_schedule_breaks', function (Blueprint $table) {
            $table->string('schedule_type', 24)->default('lunch_break')->after('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::table('faculty_schedule_breaks', function (Blueprint $table) {
            $table->dropColumn('schedule_type');
        });

        Schema::table('faculty_schedules', function (Blueprint $table) {
            $table->dropColumn('schedule_type');
        });
    }
};
