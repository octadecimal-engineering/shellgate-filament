<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Tests\Feature;

use OctadecimalHQ\ShellGate\Tests\TestCase;

class ServeCommandTest extends TestCase
{
    public function test_serve_command_exists(): void
    {
        // ServeCommand will fail (no gateway dir in test env) but should not throw
        $this->artisan('shellgate:serve')
            ->assertFailed();
    }

    public function test_serve_command_shows_error_when_gateway_not_found(): void
    {
        $this->artisan('shellgate:serve')
            ->expectsOutputToContain('Gateway directory not found')
            ->assertFailed();
    }
}
