<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_dashboard_requires_authentication(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_forwarded_https_requests_generate_https_redirects(): void
    {
        $response = $this->withHeader('X-Forwarded-Proto', 'https')->get('/');

        $this->assertStringStartsWith('https://', $response->headers->get('Location'));
    }

    public function test_dashboard_warns_one_week_before_an_active_contract_ends(): void
    {
        $employee = Employee::create([
            'employee_no' => 'COS-WARNING-1',
            'first_name' => 'Contract',
            'last_name' => 'Reminder',
            'rate_type' => 'hourly',
            'rate_amount' => 100,
            'contract_end' => now()->addDays(7)->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('contractWarnings.0.id', $employee->id)
                ->where('contractWarnings.0.days_remaining', 7));
    }
}
