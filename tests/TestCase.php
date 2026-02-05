<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Octadecimal\ShellGate\ShellGateServiceProvider;

/**
 * Base test case for Shell Gate tests.
 *
 * @method void assertDatabaseHas(string $table, array $data, string $connection = null)
 * @method void loadLaravelMigrations(array $options = [])
 */
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations (Orchestra Testbench - loads Laravel + package migrations)
        $this->loadLaravelMigrations();

        // Add is_super_admin column to users table for tests
        $this->loadMigrationsFrom(__DIR__.'/Fixtures');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ShellGateServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('shell-gate.auth.jwt_secret', 'test-secret-key-minimum-32-characters-long');
        $app['config']->set('shell-gate.gateway.url', 'ws://localhost:7681');
        $app['config']->set('shell-gate.limits.max_sessions_per_user', 10);

        // Configure audit log channel for tests
        $app['config']->set('logging.channels.shell-gate-audit', [
            'driver' => 'single',
            'path' => storage_path('logs/terminal-audit.log'),
            'level' => 'debug',
        ]);
    }
}
