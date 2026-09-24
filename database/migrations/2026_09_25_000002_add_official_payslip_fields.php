<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('tin_no', 30)->nullable()->after('contact_no');
            $table->string('gsis_no', 30)->nullable()->after('tin_no');
            $table->string('pag_ibig_no', 30)->nullable()->after('gsis_no');
            $table->string('philhealth_no', 30)->nullable()->after('pag_ibig_no');
        });

        Schema::table('payroll_records', function (Blueprint $table) {
            $table->decimal('overtime_pay', 10, 2)->default(0)->after('gross_pay');
            $table->unsignedInteger('late_undertime_minutes')->default(0)->after('overtime_pay');
            $table->decimal('absent_days', 6, 2)->default(0)->after('late_undertime_minutes');
            $table->decimal('other_earnings', 10, 2)->default(0)->after('absent_days');
            $table->decimal('increase_amount', 10, 2)->default(0)->after('other_earnings');
            $table->decimal('total_earnings', 10, 2)->default(0)->after('increase_amount');
            $table->decimal('withholding_tax', 10, 2)->default(0)->after('total_earnings');
            $table->decimal('gsis_deduction', 10, 2)->default(0)->after('withholding_tax');
            $table->decimal('philhealth_deduction', 10, 2)->default(0)->after('gsis_deduction');
            $table->decimal('pag_ibig_deduction', 10, 2)->default(0)->after('philhealth_deduction');
            $table->decimal('multi_purpose_loan', 10, 2)->default(0)->after('pag_ibig_deduction');
            $table->decimal('gsis_loan', 10, 2)->default(0)->after('multi_purpose_loan');
            $table->decimal('gsis_eplus_loan', 10, 2)->default(0)->after('gsis_loan');
            $table->decimal('fea_dues', 10, 2)->default(0)->after('gsis_eplus_loan');
            $table->decimal('oba_deduction', 10, 2)->default(0)->after('fea_dues');
            $table->decimal('cra_deduction', 10, 2)->default(0)->after('oba_deduction');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_records', function (Blueprint $table) {
            $table->dropColumn([
                'overtime_pay', 'late_undertime_minutes', 'absent_days', 'other_earnings',
                'increase_amount', 'total_earnings', 'withholding_tax', 'gsis_deduction',
                'philhealth_deduction', 'pag_ibig_deduction', 'multi_purpose_loan',
                'gsis_loan', 'gsis_eplus_loan', 'fea_dues', 'oba_deduction', 'cra_deduction',
            ]);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['tin_no', 'gsis_no', 'pag_ibig_no', 'philhealth_no']);
        });
    }
};
