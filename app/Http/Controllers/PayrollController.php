<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use App\Services\AttendanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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
            $lateUndertimeMinutes = $logs->sum('late_minutes') + $logs->sum('undertime_minutes');
            $scheduledDays = 0;
            for ($date = Carbon::parse($period->start_date); $date->lte(Carbon::parse($period->end_date)); $date->addDay()) {
                if (AttendanceCalculator::scheduledHoursForDay($employee, $date) > 0) {
                    $scheduledDays++;
                }
            }
            $absentDays = max(0, $scheduledDays - $daysWorked);
            $rateType = $employee->rate_type;
            $rateAmount = (float) $employee->rate_amount;

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
                    'overtime_pay' => 0,
                    'late_undertime_minutes' => $lateUndertimeMinutes,
                    'absent_days' => $absentDays,
                    'other_earnings' => 0,
                    'increase_amount' => 0,
                    'total_earnings' => $grossPay,
                    'withholding_tax' => 0,
                    'gsis_deduction' => 0,
                    'philhealth_deduction' => 0,
                    'pag_ibig_deduction' => 0,
                    'multi_purpose_loan' => 0,
                    'gsis_loan' => 0,
                    'gsis_eplus_loan' => 0,
                    'fea_dues' => 0,
                    'oba_deduction' => 0,
                    'cra_deduction' => 0,
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

    public function update(Request $request, PayrollRecord $record)
    {
        $fields = [
            'overtime_pay', 'other_earnings', 'increase_amount', 'withholding_tax',
            'gsis_deduction', 'philhealth_deduction', 'pag_ibig_deduction',
            'multi_purpose_loan', 'gsis_loan', 'gsis_eplus_loan', 'fea_dues',
            'oba_deduction', 'cra_deduction',
        ];

        $rules = array_fill_keys($fields, ['required', 'numeric', 'min:0', 'max:99999999.99']);
        $data = $request->validate($rules);
        $totalEarnings = round((float) $record->gross_pay
            + (float) $data['overtime_pay']
            + (float) $data['other_earnings']
            + (float) $data['increase_amount'], 2);
        $deductionFields = array_slice($fields, 3);
        $totalDeductions = round(array_sum(array_map(fn ($field) => (float) $data[$field], $deductionFields)), 2);

        $record->update([
            ...$data,
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'total_adjustments' => round((float) $data['overtime_pay'] + (float) $data['other_earnings'] + (float) $data['increase_amount'], 2),
            'net_pay' => max(0, round($totalEarnings - $totalDeductions, 2)),
        ]);

        return redirect()->route('payroll.records.show', $record)->with('success', 'Payslip details updated.');
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
