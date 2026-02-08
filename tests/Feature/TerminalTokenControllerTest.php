<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Tests\Feature;

use OctadecimalHQ\ShellGate\Models\TerminalSession;
use OctadecimalHQ\ShellGate\Tests\Fixtures\User;
use OctadecimalHQ\ShellGate\Tests\TestCase;

class TerminalTokenControllerTest extends TestCase
{
    public function test_unauthenticated_user_cannot_get_token(): void
    {
        $response = $this->postJson('/api/terminal/token');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_token(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/terminal/token');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'expires_at',
                'session_id',
                'gateway_url',
            ]);
    }

    public function test_creates_session_on_token_request(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($user)
            ->postJson('/api/terminal/token');

        $this->assertDatabaseHas('terminal_sessions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_rate_limiting_works(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        // Make requests up to the limit
        for ($i = 0; $i < 5; $i++) {
            $response = $this->actingAs($user)
                ->postJson('/api/terminal/token');

            $response->assertStatus(200);
        }

        // Next request should be rate limited
        $response = $this->actingAs($user)
            ->postJson('/api/terminal/token');

        $response->assertStatus(429)
            ->assertJsonStructure(['error', 'retry_after']);
    }

    public function test_session_limit_enforced(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        // Create sessions up to the limit
        $maxSessions = config('shell-gate.limits.max_sessions_per_user', 3);

        for ($i = 0; $i < $maxSessions; $i++) {
            TerminalSession::create([
                'user_id' => $user->id,
                'session_id' => "existing-session-{$i}",
                'started_at' => now(),
            ]);
        }

        // Request should fail due to session limit
        $response = $this->actingAs($user)
            ->postJson('/api/terminal/token');

        $response->assertStatus(429)
            ->assertJson(['error' => 'Maximum session limit reached']);
    }
}
