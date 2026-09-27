<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FacultyRank;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HardwareAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hardware_endpoint_rejects_requests_when_server_key_is_missing(): void
    {
        config(['services.hardware.api_key' => null]);

        $this->postJson('/api/hardware/tap', [
            'identifier' => 'RFID-1001',
            'method' => 'rfid',
        ])->assertUnauthorized();
    }

    public function test_hardware_endpoint_rejects_an_incorrect_key(): void
    {
        config(['services.hardware.api_key' => 'correct-key']);

        $this->postJson('/api/hardware/tap', [
            'api_key' => 'incorrect-key',
            'identifier' => 'RFID-1001',
            'method' => 'rfid',
        ])->assertUnauthorized();
    }

    public function test_hardware_endpoint_accepts_the_configured_key(): void
    {
        config(['services.hardware.api_key' => 'correct-key']);

        $this->postJson('/api/hardware/tap', [
            'api_key' => 'correct-key',
            'identifier' => 'UNKNOWN-RFID',
            'method' => 'rfid',
        ])->assertNotFound();
    }

    public function test_hardware_records_an_unscheduled_tap_without_counting_payable_hours(): void
    {
        config(['services.hardware.api_key' => 'device-secret']);
        Carbon::setTestNow(Carbon::parse('2026-09-20 08:00:00', 'Asia/Manila'));
        $rank = FacultyRank::where('is_active', true)->firstOrFail();
        $employee = Employee::create([
            'employee_no' => 'COS-9999',
            'first_name' => 'No',
            'last_name' => 'Schedule',
            'faculty_rank_id' => $rank->id,
            'rate_type' => 'hourly',
            'rate_amount' => $rank->rate_amount,
            'status' => 'active',
        ]);
        $employee->rfidCards()->create([
            'rfid_uid' => 'NO-SCHEDULE-CARD',
            'status' => 'active',
        ]);

        $this->withHeader('X-Hardware-Key', 'device-secret')
            ->postJson('/api/hardware/tap', [
                'identifier' => 'NO-SCHEDULE-CARD',
                'method' => 'rfid',
            ])
            ->assertOk()
            ->assertJsonPath('action', 'IN');

        $log = AttendanceLog::whereBelongsTo($employee)->firstOrFail();
        $this->assertSame(0.0, (float) $log->total_hours);
        $this->assertStringContainsString('no payable hours counted', $log->remarks);

        Carbon::setTestNow();
    }

    public function test_hardware_allows_time_out_after_three_minutes(): void
    {
        config(['services.hardware.api_key' => 'device-secret']);
        $startedAt = Carbon::parse('2026-09-21 08:00:00', 'Asia/Manila');
        Carbon::setTestNow($startedAt);
        $rank = FacultyRank::where('is_active', true)->firstOrFail();
        $employee = Employee::create([
            'employee_no' => 'COS-COOLDOWN-1',
            'first_name' => 'Three',
            'last_name' => 'Minutes',
            'faculty_rank_id' => $rank->id,
            'rate_type' => 'hourly',
            'rate_amount' => $rank->rate_amount,
            'status' => 'active',
        ]);
        $employee->rfidCards()->create([
            'rfid_uid' => 'THREE-MINUTE-CARD',
            'status' => 'active',
        ]);
        $tap = fn () => $this->withHeader('X-Hardware-Key', 'device-secret')
            ->postJson('/api/hardware/tap', [
                'identifier' => 'THREE-MINUTE-CARD',
                'method' => 'rfid',
            ]);

        $tap()->assertOk()->assertJsonPath('action', 'IN');
        Carbon::setTestNow($startedAt->copy()->addMinutes(2)->addSeconds(59));
        $tap()->assertOk()->assertJsonPath('action', 'WAIT');
        Carbon::setTestNow($startedAt->copy()->addMinutes(3));
        $tap()->assertOk()->assertJsonPath('action', 'OUT');

        Carbon::setTestNow();
    }
}
