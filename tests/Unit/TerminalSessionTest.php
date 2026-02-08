<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Tests\Unit;

use OctadecimalHQ\ShellGate\Models\TerminalSession;
use OctadecimalHQ\ShellGate\Tests\Fixtures\User;
use OctadecimalHQ\ShellGate\Tests\TestCase;
use PHPUnit\Framework\Assert;

class TerminalSessionTest extends TestCase
{
    public function test_creates_session(): void
    {
        $user = User::factory()->create();

        $session = TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'started_at' => now(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent/1.0',
        ]);

        $this->assertDatabaseHas('terminal_sessions', [
            'session_id' => 'test-session-id',
            'user_id' => $user->id,
        ]);
    }

    public function test_session_is_active_when_not_ended(): void
    {
        $user = User::factory()->create();

        $session = TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'started_at' => now(),
        ]);

        Assert::assertTrue($session->isActive());
    }

    public function test_session_is_not_active_when_ended(): void
    {
        $user = User::factory()->create();

        $session = TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        Assert::assertFalse($session->isActive());
    }

    public function test_active_scope_filters_correctly(): void
    {
        $user = User::factory()->create();

        // Create active session
        TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => 'active-session',
            'started_at' => now(),
        ]);

        // Create ended session
        TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => 'ended-session',
            'started_at' => now(),
            'ended_at' => now(),
        ]);

        $activeSessions = TerminalSession::active()->get();

        Assert::assertCount(1, $activeSessions);
        Assert::assertEquals('active-session', $activeSessions->first()->session_id);
    }

    public function test_for_user_scope_filters_correctly(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        TerminalSession::create([
            'user_id' => $user1->id,
            'session_id' => 'user1-session',
            'started_at' => now(),
        ]);

        TerminalSession::create([
            'user_id' => $user2->id,
            'session_id' => 'user2-session',
            'started_at' => now(),
        ]);

        $user1Sessions = TerminalSession::forUser($user1->id)->get();

        Assert::assertCount(1, $user1Sessions);
        Assert::assertEquals('user1-session', $user1Sessions->first()->session_id);
    }

    public function test_end_method_updates_session(): void
    {
        $user = User::factory()->create();

        $session = TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'started_at' => now(),
        ]);

        Assert::assertTrue($session->isActive());

        $session->end('user');

        $session->refresh();

        Assert::assertFalse($session->isActive());
        Assert::assertNotNull($session->ended_at);
        Assert::assertEquals('user', $session->end_reason);
    }

    public function test_calculates_duration(): void
    {
        $user = User::factory()->create();

        $startTime = now()->subMinutes(5);
        $endTime = now();

        $session = TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => 'test-session-id',
            'started_at' => $startTime,
            'ended_at' => $endTime,
        ]);

        $duration = $session->getDurationInSeconds();

        // Should be approximately 300 seconds (5 minutes)
        Assert::assertGreaterThanOrEqual(299, $duration);
        Assert::assertLessThanOrEqual(301, $duration);
    }
}
