<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FacultyRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EmployeeCredentialTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_faculty_details_does_not_change_device_registration_time(): void
    {
        $employee = $this->employee();
        $registeredAt = Carbon::now()->subDay()->startOfSecond();
        $employee->rfidCards()->create([
            'rfid_uid' => 'CARD-1001',
            'status' => 'active',
            'registered_at' => $registeredAt,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('employees.update', $employee), $this->employeePayload($employee, [
                'rfid_uid' => 'CARD-1001',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $registeredAt->toDateTimeString(),
            $employee->rfidCards()->firstOrFail()->registered_at
        );
    }

    public function test_admin_can_reregister_an_existing_rfid_card(): void
    {
        $employee = $this->employee();
        $registeredAt = Carbon::now()->subDay()->startOfSecond();
        $employee->rfidCards()->create([
            'rfid_uid' => 'CARD-1001',
            'status' => 'active',
            'registered_at' => $registeredAt,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('employees.update', $employee), $this->employeePayload($employee, [
                'rfid_uid' => 'CARD-1001',
                'rfid_reregister' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            Carbon::parse($employee->rfidCards()->firstOrFail()->registered_at)->greaterThan($registeredAt)
        );
    }

    private function employee(): Employee
    {
        $rank = FacultyRank::where('is_active', true)->firstOrFail();

        return Employee::create([
            'employee_no' => 'COS-9001',
            'first_name' => 'Test',
            'last_name' => 'Faculty',
            'faculty_rank_id' => $rank->id,
            'position' => 'COS Faculty Member',
            'department' => 'Department of Computer Studies',
            'employment_type' => 'Contract of Service',
            'rate_type' => 'hourly',
            'rate_amount' => $rank->rate_amount,
            'status' => 'active',
        ]);
    }

    private function employeePayload(Employee $employee, array $overrides = []): array
    {
        return array_merge([
            'employee_no' => $employee->employee_no,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'faculty_rank_id' => $employee->faculty_rank_id,
            'status' => $employee->status,
            'rfid_uid' => '',
            'fingerprint_code' => '',
            'finger_label' => '',
        ], $overrides);
    }
}
