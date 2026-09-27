<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('admin')->after('password');
            }
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_no', 30)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('email')->nullable();
            $table->string('contact_no', 20)->nullable();
            $table->string('position')->default('COS Faculty Member');
            $table->string('department')->default('Department of Computer Studies');
            $table->string('employment_type')->default('Contract of Service');
            $table->enum('rate_type', ['daily', 'hourly'])->default('daily');
            $table->decimal('rate_amount', 10, 2);
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('id')->constrained('employees')->nullOnDelete();
            }
        });

        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('rfid_uid')->unique();
            $table->enum('status', ['active', 'lost', 'inactive'])->default('active');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('fingerprint_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('fingerprint_code')->unique();
            $table->string('finger_label', 50)->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->time('time_in')->nullable();
            $table->time('time_out')->nullable();
            $table->enum('method_in', ['rfid', 'fingerprint', 'manual'])->nullable();
            $table->enum('method_out', ['rfid', 'fingerprint', 'manual'])->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('undertime_minutes')->default(0);
            $table->decimal('total_hours', 6, 2)->default(0);
            $table->enum('status', ['present', 'late', 'absent', 'undertime', 'incomplete'])->default('incomplete');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
        });

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->date('pay_date')->nullable();
            $table->enum('status', ['open', 'processing', 'finalized'])->default('open');
            $table->timestamps();
        });

        Schema::create('payroll_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->decimal('total_days_worked', 6, 2)->default(0);
            $table->decimal('total_hours_worked', 8, 2)->default(0);
            $table->decimal('gross_pay', 10, 2)->default(0);
            $table->decimal('total_deductions', 10, 2)->default(0);
            $table->decimal('total_adjustments', 10, 2)->default(0);
            $table->decimal('net_pay', 10, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'released'])->default('draft');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('action');
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }
        });

        Schema::dropIfExists('audit_trails');
        Schema::dropIfExists('payroll_records');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('attendance_logs');
        Schema::dropIfExists('fingerprint_templates');
        Schema::dropIfExists('rfid_cards');
        Schema::dropIfExists('employees');
    }
};
