<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Tests\Unit;

use OctadecimalHQ\ShellGate\Services\JwtService;
use OctadecimalHQ\ShellGate\Tests\TestCase;

class JwtServiceTest extends TestCase
{
    private JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jwtService = new JwtService(
            secret: 'test-secret-key-minimum-32-characters-long',
            ttl: 300,
            bindIp: true,
            bindUserAgent: true,
        );
    }

    public function test_generates_valid_token(): void
    {
        $result = $this->jwtService->generate(
            payload: ['user_id' => 1, 'session_id' => 'test-session'],
            ip: '127.0.0.1',
            userAgent: 'TestAgent/1.0',
        );

        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('session_id', $result);
        $this->assertIsString($result['token']);
        $this->assertIsInt($result['expires_at']);
    }

    public function test_validates_token_successfully(): void
    {
        $result = $this->jwtService->generate(
            payload: ['user_id' => 1],
            ip: '127.0.0.1',
            userAgent: 'TestAgent/1.0',
        );

        $payload = $this->jwtService->validate(
            token: $result['token'],
            ip: '127.0.0.1',
            userAgent: 'TestAgent/1.0',
        );

        $this->assertEquals(1, $payload->user_id);
    }

    public function test_rejects_invalid_ip(): void
    {
        $result = $this->jwtService->generate(
            payload: ['user_id' => 1],
            ip: '127.0.0.1',
            userAgent: 'TestAgent/1.0',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('IP address mismatch');

        $this->jwtService->validate(
            token: $result['token'],
            ip: '192.168.1.1',
            userAgent: 'TestAgent/1.0',
        );
    }

    public function test_rejects_invalid_user_agent(): void
    {
        $result = $this->jwtService->generate(
            payload: ['user_id' => 1],
            ip: '127.0.0.1',
            userAgent: 'TestAgent/1.0',
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User-Agent mismatch');

        $this->jwtService->validate(
            token: $result['token'],
            ip: '127.0.0.1',
            userAgent: 'DifferentAgent/2.0',
        );
    }

    public function test_token_expires_correctly(): void
    {
        $shortTtlService = new JwtService(
            secret: 'test-secret-key-minimum-32-characters-long',
            ttl: 1,
            bindIp: false,
            bindUserAgent: false,
        );

        $result = $shortTtlService->generate(['user_id' => 1]);

        // Token should be valid immediately
        $payload = $shortTtlService->validate($result['token']);
        $this->assertEquals(1, $payload->user_id);

        // Wait for expiration
        sleep(2);

        $this->expectException(\Firebase\JWT\ExpiredException::class);
        $shortTtlService->validate($result['token']);
    }

    public function test_generates_unique_session_ids(): void
    {
        $result1 = $this->jwtService->generate(['user_id' => 1]);
        $result2 = $this->jwtService->generate(['user_id' => 1]);

        $this->assertNotEquals($result1['session_id'], $result2['session_id']);
    }
}
