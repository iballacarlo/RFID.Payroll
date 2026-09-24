<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
