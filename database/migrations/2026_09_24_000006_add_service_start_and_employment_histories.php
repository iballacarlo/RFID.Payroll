<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('service_start_date')->nullable()->after('years_of_service');
        });

        Schema::create('employment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('employer');
            $table->string('position');
            $table->date('started_on');
            $table->date('ended_on')->nullable();
            $table->timestamps();
        });

        DB::table('employees')->whereNotNull('years_of_service')->orderBy('id')->each(function ($employee) {
            $months = (int) round(((float) $employee->years_of_service) * 12);
            DB::table('employees')->where('id', $employee->id)->update([
                'service_start_date' => now()->startOfMonth()->subMonths($months)->toDateString(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employment_histories');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('service_start_date');
        });
    }
};
