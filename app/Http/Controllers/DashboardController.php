<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = Carbon::today()->toDateString();
        $user = Auth::user();

        if ($user->role === 'faculty') {
            $employeeId = $user->employee_id;

            return Inertia::render('Dashboard', [
                'attendanceTrend' => $this->attendanceTrend($employeeId),
                'latestPayrolls' => $employeeId ? PayrollRecord::with(['employee', 'payrollPeriod'])->where('employee_id', $employeeId)->latest()->take(5)->get() : collect(),
                'recentAttendance' => $employeeId ? AttendanceLog::with('employee')->where('employee_id', $employeeId)->latest()->take(8)->get() : collect(),
            ]);
        }

        return Inertia::render('Dashboard', [
            'employeeCount' => Employee::count(),
            'presentToday' => AttendanceLog::where('attendance_date', $today)->whereNotNull('time_in')->count(),
            'openPeriods' => PayrollPeriod::where('status', '!=', 'finalized')->count(),
            'attendanceTrend' => $this->attendanceTrend(),
            'latestPayrolls' => PayrollRecord::with(['employee', 'payrollPeriod'])->latest()->take(5)->get(),
            'recentAttendance' => AttendanceLog::with('employee')->latest()->take(8)->get(),
        ]);
    }

    private function attendanceTrend(?int $employeeId = null): array
    {
        return collect(range(6, 0))->map(function (int $daysAgo) use ($employeeId) {
            $date = Carbon::today()->subDays($daysAgo);
            $count = AttendanceLog::query()
                ->when($employeeId, fn ($query) => $query->where('employee_id', $employeeId))
                ->where('attendance_date', $date->toDateString())
                ->whereNotNull('time_in')
                ->count();

            return ['label' => $date->format('D'), 'count' => $count];
        })->all();
    }
}
