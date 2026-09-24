<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\RfidCard;
use App\Services\AttendanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    private const TIME_OUT_COOLDOWN_MINUTES = 5;

    public function index()
    {
        $query = AttendanceLog::with('employee')->latest();

        if (Auth::user()->role === 'faculty') {
            $query->where('employee_id', Auth::user()->employee_id);
        }

        return Inertia::render('Attendance/Index', [
            'employees' => Employee::where('status', 'active')->orderBy('last_name')->get(),
            'logs' => $query->paginate(15),
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
}
