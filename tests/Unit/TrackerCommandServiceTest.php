<?php

namespace Tests\Unit;

use App\Services\TrackerCommandService;
use PHPUnit\Framework\TestCase;

class TrackerCommandServiceTest extends TestCase
{
    public function test_rejects_protocol_delimiters_in_apn(): void
    {
        $service = new TrackerCommandService();

        $this->expectException(\InvalidArgumentException::class);

        $service->generate('ST300', [
            'serial' => '123456',
            'apn' => 'internet;Reboot',
            'host' => '127.0.0.1',
            'port' => 9601,
        ]);
    }

    public function test_rejects_invalid_port(): void
    {
        $service = new TrackerCommandService();

        $this->expectException(\InvalidArgumentException::class);

        $service->generate('ST390', [
            'serial' => '123456',
            'host' => 'example.com',
            'port' => 70000,
        ]);
    }

    public function test_generates_known_command_with_valid_input(): void
    {
        $service = new TrackerCommandService();

        $commands = $service->generate('ST4315U', [
            'esn' => '1234567890',
            'host' => 'example.com',
            'port' => 13018,
            'auth' => 'CHAP',
        ]);

        $this->assertArrayHasKey('Solicitar posição', $commands);
        $this->assertSame('CMD;1234567890;03;01', $commands['Solicitar posição']);
    }
}
