<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Tests\Feature;

use OctadecimalHQ\ShellGate\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    public function test_install_command_exists(): void
    {
        $this->artisan('shellgate:install', ['--no-migrate' => true, '--no-gateway' => true])
            ->assertSuccessful();
    }

    public function test_install_command_publishes_config(): void
    {
        $configPath = config_path('shell-gate.php');

        // Remove if exists from previous test
        if (file_exists($configPath)) {
            unlink($configPath);
        }

        $this->artisan('shellgate:install', ['--no-migrate' => true, '--no-gateway' => true])
            ->assertSuccessful();

        $this->assertFileExists($configPath);

        // Cleanup
        unlink($configPath);
    }

    public function test_install_command_skips_existing_config(): void
    {
        $configPath = config_path('shell-gate.php');

        // Create a dummy config
        if (! is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        file_put_contents($configPath, '<?php return [];');

        $this->artisan('shellgate:install', ['--no-migrate' => true, '--no-gateway' => true])
            ->assertSuccessful();

        // Config should not be overwritten
        $this->assertEquals('<?php return [];', file_get_contents($configPath));

        // Cleanup
        unlink($configPath);
    }

    public function test_install_command_force_overwrites_config(): void
    {
        $configPath = config_path('shell-gate.php');

        // Create a dummy config
        if (! is_dir(dirname($configPath))) {
            mkdir(dirname($configPath), 0755, true);
        }
        file_put_contents($configPath, '<?php return [];');

        $this->artisan('shellgate:install', [
            '--no-migrate' => true,
            '--no-gateway' => true,
            '--force' => true,
        ])->assertSuccessful();

        // Config should be overwritten with actual config
        $this->assertNotEquals('<?php return [];', file_get_contents($configPath));

        // Cleanup
        unlink($configPath);
    }

    public function test_install_command_adds_env_variable(): void
    {
        $envPath = base_path('.env');
        $originalContent = file_exists($envPath) ? file_get_contents($envPath) : '';

        // Ensure no SHELL_GATE_GATEWAY_URL exists
        $cleanContent = preg_replace('/^.*SHELL_GATE_GATEWAY_URL.*$/m', '', $originalContent);
        $cleanContent = preg_replace('/^# Shell Gate$/m', '', $cleanContent);
        file_put_contents($envPath, trim($cleanContent) . "\n");

        $this->artisan('shellgate:install', ['--no-migrate' => true, '--no-gateway' => true])
            ->assertSuccessful();

        $envContent = file_get_contents($envPath);
        $this->assertStringContainsString('SHELL_GATE_GATEWAY_URL=ws://127.0.0.1:7681', $envContent);

        // Restore
        file_put_contents($envPath, $originalContent);
    }
}
