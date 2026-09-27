<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payroll_periods')->orderBy('id')->chunkById(100, function ($periods) {
            foreach ($periods as $period) {
                $start = Carbon::parse($period->start_date);
                $end = Carbon::parse($period->end_date);

                if (! $start->isSameMonth($end) || ! $start->isSameYear($end)) {
                    continue;
                }

                $payDate = match (true) {
                    $start->day === 1 && $end->day === 15 => $start->copy()->day(25),
                    $start->day === 16 && $end->day === $start->daysInMonth => $start->copy()->addMonthNoOverflow()->day(10),
                    default => null,
                };

                if ($payDate && $period->pay_date !== $payDate->toDateString()) {
                    DB::table('payroll_periods')->where('id', $period->id)->update(['pay_date' => $payDate->toDateString()]);
                }
            }
        });
    }

    public function down(): void
    {
        // The previous pay dates cannot be reconstructed reliably.
    }
};
