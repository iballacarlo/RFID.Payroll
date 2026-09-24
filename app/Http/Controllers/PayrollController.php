<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Services\AttendanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PayrollController extends Controller
{
    public function index()
    {
        $records = PayrollRecord::with(['employee', 'payrollPeriod'])->latest();

        if (Auth::user()->role === 'faculty') {
            $records->where('employee_id', Auth::user()->employee_id);
        }

        return Inertia::render('Payroll/Index', [
            'periods' => PayrollPeriod::withCount('records')->latest()->get(),
            'records' => $records->paginate(15),
        ]);
    }

    public function storePeriod(Request $request)
    {
        $data = $request->validate([
            'period_name' => ['required', 'max:100'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'pay_date' => ['nullable', 'date'],
        ]);

        $expectedPayDate = $this->expectedPayDate($data['start_date'], $data['end_date']);

        if (! $expectedPayDate) {
            throw ValidationException::withMessages([
                'start_date' => 'Payroll cutoff must be either day 1 to 15, or day 16 to the last day of the same month.',
                'end_date' => 'Payroll cutoff must be either day 1 to 15, or day 16 to the last day of the same month.',
            ]);
        }

        $data['pay_date'] = $expectedPayDate;

        PayrollPeriod::create($data);

        return redirect()->route('payroll.index')->with('success', 'Payroll period created.');
    }

    public function generate(PayrollPeriod $period)
    {
        $employees = Employee::with(['facultyRank', 'schedules'])->where('status', 'active')->get();

        foreach ($employees as $employee) {
            $logs = AttendanceLog::with('employee.schedules')->where('employee_id', $employee->id)
                ->whereBetween('attendance_date', [$period->start_date, $period->end_date])
                ->get();

            $daysWorked = $logs->filter(fn ($log) => (float) $log->total_hours > 0)->count();
            $hoursWorked = $logs->sum('total_hours');
            $rateType = $employee->facultyRank?->rate_type ?? $employee->rate_type;
            $rateAmount = (float) ($employee->facultyRank?->rate_amount ?? $employee->rate_amount);

            if ($rateType === 'hourly') {
                $grossPay = round($hoursWorked * $rateAmount, 2);
            } else {
                // A complete scheduled shift earns one daily rate; partial shifts are prorated by that day's schedule.
                $grossPay = round($logs->sum(function ($log) use ($employee, $rateAmount) {
                    $scheduledHours = AttendanceCalculator::scheduledHoursForDay($employee, $log->attendance_date);

                    return $scheduledHours > 0 ? ((float) $log->total_hours / $scheduledHours) * $rateAmount : 0;
                }), 2);
            }

            // Late, undertime, early arrival, and overtime are already reflected in payable scheduled hours.
            $deductions = 0;
            $netPay = max(0, $grossPay);

            PayrollRecord::updateOrCreate(
                ['payroll_period_id' => $period->id, 'employee_id' => $employee->id],
                [
                    'total_days_worked' => $daysWorked,
                    'total_hours_worked' => $hoursWorked,
                    'gross_pay' => $grossPay,
                    'total_deductions' => $deductions,
                    'total_adjustments' => 0,
                    'net_pay' => $netPay,
                    'status' => 'draft',
                    'generated_at' => Carbon::now(),
                ]
            );
        }

        $period->update(['status' => 'processing']);

        return redirect()->route('payroll.index')->with('success', 'Payroll generated from attendance logs.');
    }

    public function show(PayrollRecord $record)
    {
        $record->load(['employee', 'payrollPeriod']);

        if (Auth::user()->role === 'faculty' && Auth::user()->employee_id !== $record->employee_id) {
            abort(403, 'You can only view your own payslip.');
        }

        return Inertia::render('Payroll/Show', ['record' => $record]);
    }

    private function expectedPayDate(string $startDate, string $endDate): ?string
    {
        $start = Carbon::parse($startDate, 'Asia/Manila');
        $end = Carbon::parse($endDate, 'Asia/Manila');

        if (! $start->isSameMonth($end) || ! $start->isSameYear($end)) {
            return null;
        }

        if ($start->day === 1 && $end->day === 15) {
            return $start->copy()->day(25)->toDateString();
        }

        if ($start->day === 16 && $end->day === $start->daysInMonth) {
            return $start->copy()->addMonthNoOverflow()->day(10)->toDateString();
        }

        return null;
    }
}
