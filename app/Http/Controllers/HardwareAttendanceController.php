<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\RfidCard;
use App\Services\AttendanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class HardwareAttendanceController extends Controller
{
    private const TIME_OUT_COOLDOWN_MINUTES = 5;

    public function tap(Request $request)
    {
        if ($request->input('api_key') !== env('HARDWARE_API_KEY')) {
            return response()->json([
                'ok' => false,
                'message' => 'Invalid hardware API key.',
            ], 401);
        }

        $data = $request->validate([
            'identifier' => ['required', 'max:100'],
            'method' => ['required', 'in:rfid,fingerprint'],
        ]);

        $employee = $data['method'] === 'rfid'
            ? optional(RfidCard::where('rfid_uid', $data['identifier'])->where('status', 'active')->first())->employee
            : optional(FingerprintTemplate::where('fingerprint_code', $data['identifier'])->where('status', 'active')->first())->employee;

        if (! $employee || $employee->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'RFID/fingerprint is not registered to an active faculty member.',
            ], 404);
        }

        $now = Carbon::now('Asia/Manila');

        if (! AttendanceCalculator::hasSchedule($employee, $now)) {
            return response()->json([
                'ok' => false,
                'code' => 'no_schedule',
                'message' => 'No schedule is assigned for this faculty member today.',
                'display_name' => $this->displayName($employee),
                'action' => 'NO SCHEDULE',
            ], 409);
        }

        $result = $this->recordAttendance($employee->id, $data['method']);

        return response()->json([
            'ok' => true,
            'message' => $result['message'],
            'employee' => $employee->full_name,
            'display_name' => $this->displayName($employee),
            'action' => $result['action'],
            'display_time' => $result['time'],
            'status' => $result['log']->status,
            'time_in' => $result['log']->time_in,
            'time_out' => $result['log']->time_out,
        ]);
    }

    private function recordAttendance(int $employeeId, string $method): array
    {
        $now = Carbon::now('Asia/Manila');
        $log = AttendanceLog::firstOrNew([
            'employee_id' => $employeeId,
            'attendance_date' => $now->toDateString(),
        ]);

        if (! $log->time_in) {
            $log->time_in = $now->format('H:i:s');
            $log->method_in = $method;
            $message = 'Time in recorded.';
            $action = 'IN';
            $time = $now->format('h:i A');
        } elseif (! $log->time_out) {
            $timeIn = Carbon::parse($log->attendance_date.' '.$log->time_in, 'Asia/Manila');
            $canTimeOutAt = $timeIn->copy()->addMinutes(self::TIME_OUT_COOLDOWN_MINUTES);

            if ($now->lessThan($canTimeOutAt)) {
                $message = 'Please wait before recording time out.';
                $action = 'WAIT';
                $time = $canTimeOutAt->format('h:i A');

                return ['message' => $message, 'action' => $action, 'time' => $time, 'log' => $log];
            }

            $log->time_out = $now->format('H:i:s');
            $log->method_out = $method;
            $message = 'Time out recorded.';
            $action = 'OUT';
            $time = $now->format('h:i A');
        } else {
            $message = 'Attendance already has time in and time out for today.';
            $action = 'ALREADY OUT';
            $time = Carbon::parse($log->attendance_date.' '.$log->time_out, 'Asia/Manila')->format('h:i A');
        }

        $log->save();
        $this->recalculate($log);

        return ['message' => $message, 'action' => $action, 'time' => $time, 'log' => $log->fresh()];
    }

    private function displayName(Employee $employee): string
    {
        $name = $employee->full_name;
        $name = $this->lcdSafeText($name);

        // The ESP32 decides whether a long name should scroll on its 16-character LCD.
        return $name;
    }

    private function lcdSafeText(string $value): string
    {
        $value = strtr($value, [
            'ñ' => 'n',
            'Ñ' => 'N',
            'á' => 'a',
            'Á' => 'A',
            'é' => 'e',
            'É' => 'E',
            'í' => 'i',
            'Í' => 'I',
            'ó' => 'o',
            'Ó' => 'O',
            'ú' => 'u',
            'Ú' => 'U',
        ]);

        return preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';
    }

    private function recalculate(AttendanceLog $log): void
    {
        AttendanceCalculator::recalculate($log);
    }
}
