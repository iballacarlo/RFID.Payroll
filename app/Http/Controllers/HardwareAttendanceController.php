<?php

namespace App\Http\Controllers;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FingerprintTemplate;
use App\Models\HardwareEnrollment;
use App\Models\RfidCard;
use App\Services\AttendanceCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HardwareAttendanceController extends Controller
{
    private const TIME_OUT_COOLDOWN_MINUTES = 3;

    public function tap(Request $request)
    {
        if ($unauthorized = $this->authorizeHardware($request)) {
            return $unauthorized;
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

    public function pendingEnrollment(Request $request)
    {
        if ($unauthorized = $this->authorizeHardware($request)) {
            return $unauthorized;
        }

        HardwareEnrollment::whereIn('status', ['pending', 'processing'])
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'message' => 'The registration request expired.']);

        $enrollment = HardwareEnrollment::with('employee')
            ->whereIn('status', ['pending', 'processing'])
            ->where('expires_at', '>', now())
            ->oldest()
            ->first();

        if (! $enrollment) {
            return response()->noContent();
        }

        if ($enrollment->status === 'pending') {
            $enrollment->update(['status' => 'processing']);
        }

        return response()->json([
            'ok' => true,
            'id' => (string) $enrollment->id,
            'method' => $enrollment->method,
            'employee' => $this->displayName($enrollment->employee),
            'current_identifier' => $enrollment->current_identifier ?? '',
        ]);
    }

    public function completeEnrollment(Request $request, HardwareEnrollment $enrollment)
    {
        if ($unauthorized = $this->authorizeHardware($request)) {
            return $unauthorized;
        }

        if (! in_array($enrollment->status, ['pending', 'processing'], true) || $enrollment->expires_at->isPast()) {
            return response()->json(['ok' => false, 'message' => 'Enrollment is no longer active.'], 409);
        }

        $currentCredentialId = $enrollment->method === 'rfid'
            ? optional($enrollment->employee->rfidCards()->first())->id
            : optional($enrollment->employee->fingerprintTemplates()->first())->id;
        $table = $enrollment->method === 'rfid' ? 'rfid_cards' : 'fingerprint_templates';
        $column = $enrollment->method === 'rfid' ? 'rfid_uid' : 'fingerprint_code';

        $data = $request->validate([
            'status' => ['required', 'in:completed,failed'],
            'identifier' => [
                'nullable',
                'required_if:status,completed',
                'string',
                'max:100',
            ],
            'message' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['status'] === 'failed') {
            $enrollment->update([
                'status' => 'failed',
                'message' => $data['message'] ?? 'Hardware registration failed.',
                'completed_at' => now(),
            ]);

            return response()->json(['ok' => true, 'status' => 'failed']);
        }

        $duplicate = DB::table($table)
            ->where($column, strtoupper($data['identifier']))
            ->when($currentCredentialId, fn ($query) => $query->where('id', '!=', $currentCredentialId))
            ->exists();

        if ($duplicate) {
            $enrollment->update([
                'status' => 'failed',
                'message' => 'That credential is already assigned to another faculty member.',
                'completed_at' => now(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'That credential is already assigned to another faculty member.',
            ], 422);
        }

        DB::transaction(function () use ($data, $enrollment) {
            if ($enrollment->method === 'rfid') {
                $credential = $enrollment->employee->rfidCards()->firstOrNew();
                $credential->fill([
                    'rfid_uid' => strtoupper($data['identifier']),
                    'status' => 'active',
                    'registered_at' => now(),
                ])->save();
            } else {
                $credential = $enrollment->employee->fingerprintTemplates()->firstOrNew();
                $credential->fill([
                    'fingerprint_code' => strtoupper($data['identifier']),
                    'finger_label' => $enrollment->finger_label ?: 'Primary finger',
                    'status' => 'active',
                    'registered_at' => now(),
                ])->save();
            }

            $enrollment->update([
                'identifier' => strtoupper($data['identifier']),
                'status' => 'completed',
                'message' => 'Credential registered successfully.',
                'completed_at' => now(),
            ]);
        });

        return response()->json([
            'ok' => true,
            'status' => 'completed',
            'identifier' => strtoupper($data['identifier']),
        ]);
    }

    private function authorizeHardware(Request $request)
    {
        $expectedApiKey = config('services.hardware.api_key');
        $providedApiKey = $request->header('X-Hardware-Key', $request->input('api_key', ''));

        if (is_string($expectedApiKey)
            && $expectedApiKey !== ''
            && is_string($providedApiKey)
            && $providedApiKey !== ''
            && hash_equals($expectedApiKey, $providedApiKey)) {
            return null;
        }

        return response()->json([
            'ok' => false,
            'message' => 'Invalid hardware API key.',
        ], 401);
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
