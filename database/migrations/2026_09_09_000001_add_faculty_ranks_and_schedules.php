<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_ranks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->enum('rate_type', ['daily', 'hourly'])->default('daily');
            $table->decimal('rate_amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('faculty_rank_id')->nullable()->after('employment_type')
                ->constrained('faculty_ranks')->nullOnDelete();
        });

        Schema::create('faculty_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 Sunday through 6 Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_schedules');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faculty_rank_id');
        });

        Schema::dropIfExists('faculty_ranks');
    }
};
