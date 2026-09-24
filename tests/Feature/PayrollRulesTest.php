<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\FacultySchedule;
use App\Models\FacultyScheduleBreak;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\AttendanceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_minute_late_counts_as_one_hour_deduction(): void
    {
        $employee = $this->employeeWithMondaySchedule();
        $log = AttendanceLog::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-21',
            'time_in' => '08:01',
            'time_out' => '17:00',
        ]);

        AttendanceCalculator::recalculate($log);

        $log->refresh();
        $this->assertSame(1, $log->late_minutes);
        $this->assertSame(0, $log->undertime_minutes);
        $this->assertEquals(7.00, (float) $log->total_hours);
    }

    public function test_late_and_undertime_each_round_up_to_started_hours(): void
    {
        $employee = $this->employeeWithMondaySchedule();
        $log = AttendanceLog::create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-21',
            'time_in' => '08:01',
            'time_out' => '16:59',
        ]);

        AttendanceCalculator::recalculate($log);

        $log->refresh();
        $this->assertSame(1, $log->late_minutes);
        $this->assertSame(1, $log->undertime_minutes);
        $this->assertEquals(6.00, (float) $log->total_hours);
    }

    public function test_lunch_and_schedule_gaps_are_not_payable(): void
    {
        $employee = $this->employeeWithMondaySchedule();
        $employee->schedules()->delete();
        foreach ([['08:00', '12:00'], ['13:00', '15:00'], ['16:00', '17:00']] as [$start, $end]) {
            $employee->schedules()->create([
                'day_of_week' => 1, 'schedule_type' => 'class',
                'start_time' => $start, 'end_time' => $end, 'break_minutes' => 0,
            ]);
        }
        FacultyScheduleBreak::create([
            'employee_id' => $employee->id, 'day_of_week' => 1,
            'schedule_type' => 'lunch_break', 'start_time' => '11:30', 'end_time' => '12:30',
        ]);
        $log = AttendanceLog::create([
            'employee_id' => $employee->id, 'attendance_date' => '2026-09-21',
            'time_in' => '08:00', 'time_out' => '17:00',
        ]);

        AttendanceCalculator::recalculate($log);

        $this->assertEquals(6.5, (float) $log->fresh()->total_hours);
        $this->assertEquals(6.5, AttendanceCalculator::scheduledHoursForDay($employee, '2026-09-21'));
    }

    public function test_payroll_period_sets_pay_date_from_cutoff(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->post(route('payroll.periods.store'), [
            'period_name' => 'September 16-30, 2026',
            'start_date' => '2026-09-16',
            'end_date' => '2026-09-30',
            'pay_date' => '2026-09-30',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('payroll_periods', [
            'period_name' => 'September 16-30, 2026',
            'pay_date' => '2026-10-10',
        ]);
    }

    public function test_payroll_period_rejects_dates_outside_allowed_cutoffs(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->from(route('payroll.index'))->post(route('payroll.periods.store'), [
            'period_name' => 'Bad cutoff',
            'start_date' => '2026-09-05',
            'end_date' => '2026-09-20',
        ]);

        $response->assertRedirect(route('payroll.index'));
        $response->assertSessionHasErrors(['start_date', 'end_date']);
        $this->assertSame(0, PayrollPeriod::count());
    }

    private function employeeWithMondaySchedule(): Employee
    {
        $employee = Employee::create([
            'employee_no' => 'EMP-001',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'rate_type' => 'hourly',
            'rate_amount' => 100,
            'status' => 'active',
        ]);

        FacultySchedule::create([
            'employee_id' => $employee->id,
            'day_of_week' => 1,
            'schedule_type' => 'class',
            'start_time' => '08:00',
            'end_time' => '17:00',
            'break_minutes' => 60,
        ]);

        return $employee;
    }
}
