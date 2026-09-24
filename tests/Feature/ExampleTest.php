<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
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
}
