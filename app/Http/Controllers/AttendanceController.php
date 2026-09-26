<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\RfidCard;
use App\Services\AttendanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    private const TIME_OUT_COOLDOWN_MINUTES = 3;

    public function index(Request $request)
    {
        $user = $request->user();
        $isFaculty = $user->role === 'faculty';
        $query = AttendanceLog::with('employee')
            ->orderByDesc('attendance_date')
            ->orderByDesc('time_in');

        if ($isFaculty) {
            $query->where('employee_id', $user->employee_id);
        }

        return Inertia::render('Attendance/Index', [
            'employees' => $isFaculty ? [] : Employee::where('status', 'active')->orderBy('last_name')->get(),
            'logs' => $query->paginate(15),
            'dtr' => $isFaculty ? $this->facultyDtr($user->employee_id) : null,
        ]);
    }

    public function tap(Request $request)
    {
        $data = $request->validate([
            'identifier' => ['required', 'max:100'],
            'method' => ['required', 'in:rfid,fingerprint'],
        ]);

        $employee = $data['method'] === 'rfid'
            ? optional(RfidCard::where('rfid_uid', $data['identifier'])->where('status', 'active')->first())->employee
            : optional(FingerprintTemplate::where('fingerprint_code', $data['identifier'])->where('status', 'active')->first())->employee;

        if (! $employee) {
            return back()->withErrors(['identifier' => 'No active faculty record found for that RFID/fingerprint code.']);
        }

        if (! AttendanceCalculator::hasSchedule($employee, Carbon::now('Asia/Manila'))) {
            return back()->withErrors(['identifier' => $employee->full_name.' has no assigned schedule today. Attendance was not recorded.']);
        }

        $result = $this->recordAttendance($employee, $data['method']);

        if ($result['action'] === 'WAIT') {
            return back()->withErrors(['identifier' => 'Please wait until '.$result['available_at'].' before recording Time Out for '.$employee->full_name.'.']);
        }

        return redirect()->route('attendance.index')->with('success', $result['action'].' recorded for '.$employee->full_name.'.');
    }

    public function manual(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'attendance_date' => ['required', 'date'],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'max:500'],
        ]);

        $employee = Employee::with('schedules')->findOrFail($data['employee_id']);

        if (! $this->hasScheduleOnDate($employee, $data['attendance_date'])) {
            return back()->withErrors(['attendance_date' => $employee->full_name.' has no assigned schedule on this date. Manual attendance can only be saved on a scheduled day.']);
        }

        $log = AttendanceLog::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'attendance_date' => $data['attendance_date']],
            [
                'time_in' => $data['time_in'],
                'time_out' => $data['time_out'],
                'method_in' => $data['time_in'] ? 'manual' : null,
                'method_out' => $data['time_out'] ? 'manual' : null,
                'remarks' => $data['remarks'] ?? null,
            ]
        );

        $this->recalculate($log);

        return redirect()->route('attendance.index')->with('success', 'Manual attendance saved.');
    }

    public function generateScheduledTestAttendance(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $start = Carbon::parse($data['start_date'], 'Asia/Manila')->startOfDay();
        $end = Carbon::parse($data['end_date'], 'Asia/Manila')->startOfDay();

        if ($start->diffInDays($end) > 31) {
            return back()->withErrors(['end_date' => 'Test attendance can cover up to 31 days at a time.']);
        }

        $employee = Employee::with('schedules')->where('status', 'active')->findOrFail($data['employee_id']);
        $created = 0;
        $skipped = 0;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $schedules = $employee->schedules
                ->where('day_of_week', $date->dayOfWeek)
                ->sortBy('start_time');

            if ($schedules->isEmpty()) {
                continue;
            }

            $log = AttendanceLog::firstOrCreate(
                ['employee_id' => $employee->id, 'attendance_date' => $date->toDateString()],
                [
                    'time_in' => $schedules->first()->start_time,
                    'time_out' => $schedules->last()->end_time,
                    'method_in' => 'manual',
                    'method_out' => 'manual',
                    'remarks' => 'Generated test attendance from assigned schedule.',
                ]
            );

            if (! $log->wasRecentlyCreated) {
                $skipped++;

                continue;
            }

            $log->setRelation('employee', $employee);
            $this->recalculate($log);
            $created++;
        }

        return redirect()->route('attendance.index')->with('success', "Created {$created} scheduled test attendance log(s). Skipped {$skipped} existing log(s).");
    }

    private function recordAttendance(Employee $employee, string $method): array
    {
        $now = Carbon::now('Asia/Manila');
        $log = AttendanceLog::firstOrNew([
            'employee_id' => $employee->id,
            'attendance_date' => $now->toDateString(),
        ]);

        if (! $log->time_in) {
            $log->time_in = $now->format('H:i:s');
            $log->method_in = $method;
            $action = 'Time In';
        } elseif (! $log->time_out) {
            $timeIn = Carbon::parse($log->attendance_date.' '.$log->time_in, 'Asia/Manila');
            $canTimeOutAt = $timeIn->copy()->addMinutes(self::TIME_OUT_COOLDOWN_MINUTES);

            if ($now->lessThan($canTimeOutAt)) {
                return ['action' => 'WAIT', 'available_at' => $canTimeOutAt->format('h:i A')];
            }

            $log->time_out = $now->format('H:i:s');
            $log->method_out = $method;
            $action = 'Time Out';
        } else {
            return ['action' => 'Already Out'];
        }

        $log->save();
        $this->recalculate($log);

        return ['action' => $action];
    }

    private function recalculate(AttendanceLog $log): void
    {
        AttendanceCalculator::recalculate($log);
    }

    private function hasScheduleOnDate(Employee $employee, string $date): bool
    {
        return AttendanceCalculator::hasSchedule($employee, $date);
    }

    private function facultyDtr(?int $employeeId): ?array
    {
        if (! $employeeId) {
            return null;
        }

        $now = Carbon::now('Asia/Manila');
        $employee = Employee::with(['schedules', 'scheduleBreaks'])->find($employeeId);

        if (! $employee) {
            return null;
        }

        $periodStart = $now->day <= 15 ? $now->copy()->startOfMonth() : $now->copy()->day(16);
        $periodEnd = $now->day <= 15 ? $now->copy()->day(15) : $now->copy()->endOfMonth();
        $elapsedEnd = $now->lessThan($periodEnd) ? $now->copy() : $periodEnd->copy();
        $periodLogs = AttendanceLog::where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $daysPresent = $periodLogs->filter(fn ($log) => (float) $log->total_hours > 0 || $log->time_in)->count();
        $scheduledDays = 0;

        for ($date = $periodStart->copy(); $date->lte($elapsedEnd); $date->addDay()) {
            if (AttendanceCalculator::scheduledHoursForDay($employee, $date) > 0) {
                $scheduledDays++;
            }
        }

        $todayLog = $periodLogs->firstWhere('attendance_date', $now->toDateString());
        $todaySchedules = $employee->schedules
            ->where('day_of_week', $now->dayOfWeek)
            ->sortBy('start_time')
            ->values()
            ->map(fn ($schedule) => [
                'id' => $schedule->id,
                'type' => $schedule->schedule_type,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
            ]);

        return [
            'server_time' => $now->toIso8601String(),
            'date' => $now->toDateString(),
            'cutoff' => [
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
            ],
            'today_log' => $todayLog,
            'today_schedules' => $todaySchedules,
            'summary' => [
                'scheduled_days' => $scheduledDays,
                'days_present' => $daysPresent,
                'absent_days' => max(0, $scheduledDays - $daysPresent),
                'total_hours' => round((float) $periodLogs->sum('total_hours'), 2),
                'late_minutes' => (int) $periodLogs->sum('late_minutes'),
                'undertime_minutes' => (int) $periodLogs->sum('undertime_minutes'),
            ],
        ];
    }
}
