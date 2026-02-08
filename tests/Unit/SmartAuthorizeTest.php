<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Tests\Unit;

use OctadecimalHQ\ShellGate\ShellGatePlugin;
use OctadecimalHQ\ShellGate\Tests\Fixtures\User;
use OctadecimalHQ\ShellGate\Tests\TestCase;

class SmartAuthorizeTest extends TestCase
{
    public function test_local_env_allows_any_authenticated_user(): void
    {
        // App is in 'testing' environment by default in Orchestra Testbench
        $this->assertTrue(app()->environment(['local', 'testing']));

        $user = User::factory()->create();
        $this->actingAs($user);

        $plugin = new ShellGatePlugin();

        // No authorize callback set — should use smart default
        $this->assertTrue($plugin->isAuthorized());
    }

    public function test_local_env_denies_unauthenticated(): void
    {
        $this->assertTrue(app()->environment(['local', 'testing']));

        $plugin = new ShellGatePlugin();

        // No user authenticated
        $this->assertFalse($plugin->isAuthorized());
    }

    public function test_explicit_authorize_callback_overrides_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plugin = new ShellGatePlugin();
        $plugin->authorize(fn () => false);

        // Explicit callback returns false — should deny
        $this->assertFalse($plugin->isAuthorized());
    }

    public function test_explicit_authorize_true_allows_all(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plugin = new ShellGatePlugin();
        $plugin->authorize(true);

        $this->assertTrue($plugin->isAuthorized());
    }

    public function test_explicit_authorize_false_denies_all(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $plugin = new ShellGatePlugin();
        $plugin->authorize(false);

        $this->assertFalse($plugin->isAuthorized());
    }

    public function test_production_env_denies_regular_user(): void
    {
        // Switch to production
        app()->detectEnvironment(fn () => 'production');
        $this->assertTrue(app()->environment('production'));

        $user = User::factory()->create(['is_super_admin' => false]);
        $this->actingAs($user);

        $plugin = new ShellGatePlugin();

        // No authorize callback + production + not super_admin = denied
        $this->assertFalse($plugin->isAuthorized());
    }

    public function test_production_env_allows_super_admin(): void
    {
        // Switch to production
        app()->detectEnvironment(fn () => 'production');
        $this->assertTrue(app()->environment('production'));

        $user = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($user);

        $plugin = new ShellGatePlugin();

        // is_super_admin = true in production = allowed
        $this->assertTrue($plugin->isAuthorized());
    }
}
