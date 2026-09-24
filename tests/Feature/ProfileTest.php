<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\FacultyRank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_their_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    public function test_user_can_update_their_account_and_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('old-password')]);

        $this->actingAs($user)->put('/profile', [
            'name' => 'Updated Administrator',
            'email' => 'updated@example.com',
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('Updated Administrator', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_faculty_can_update_personal_but_not_employment_details(): void
    {
        $rank = FacultyRank::where('name', 'Instructor I')->firstOrFail();
        $employee = Employee::create([
            'employee_no' => '2026-0001',
            'first_name' => 'Old',
            'last_name' => 'Name',
            'email' => 'old.name@cvsu.edu.ph',
            'highest_educational_attainment' => "Bachelor's Degree",
            'service_start_date' => '2025-01-01',
            'position' => 'COS Faculty Member',
            'department' => 'Department of Computer Studies',
            'employment_type' => 'Contract of Service',
            'faculty_rank_id' => $rank->id,
            'rate_type' => 'hourly',
            'rate_amount' => 250,
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'role' => 'faculty',
            'employee_id' => $employee->id,
            'email' => 'old.name@cvsu.edu.ph',
        ]);

        $this->actingAs($user)->put('/profile', [
            'name' => $user->name,
            'first_name' => 'New',
            'middle_name' => 'Middle',
            'last_name' => 'Name',
            'suffix' => '',
            'email' => 'new.name@cvsu.edu.ph',
            'contact_no' => '+639171234567',
            'rate_amount' => 9999,
            'status' => 'inactive',
            'current_password' => '',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect('/profile');

        $employee->refresh();
        $this->assertSame('New', $employee->first_name);
        $this->assertSame('+639171234567', $employee->contact_no);
        $this->assertEquals(250, $employee->rate_amount);
        $this->assertSame('active', $employee->status);
    }
}
