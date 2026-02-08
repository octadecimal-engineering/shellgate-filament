<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\error;
use function Laravel\Prompts\note;

/**
 * One-command installer for Shell Gate.
 *
 * Publishes config, runs migrations, patches AdminPanelProvider,
 * configures gateway, and installs npm dependencies.
 */
class InstallCommand extends Command
{
    protected $signature = 'shellgate:install
                            {--dev : Development mode — skip license, allow all authenticated users}
                            {--no-migrate : Skip database migrations}
                            {--no-gateway : Skip gateway setup (npm install)}
                            {--force : Overwrite existing configuration}';

    protected $description = 'Install and configure Shell Gate in your Laravel application';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->printBanner();

        // Step 1: Publish configuration
        $this->publishConfig();

        // Step 2: Run migrations
        if (! $this->option('no-migrate')) {
            $this->runMigrations();
        }

        // Step 3: Patch AdminPanelProvider
        $this->patchAdminPanelProvider();

        // Step 4: Add .env variables
        $this->configureEnv();

        // Step 5: Setup gateway
        if (! $this->option('no-gateway')) {
            $this->setupGateway();
        }

        // Summary
        $this->printSummary();

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // Step 1: Publish Configuration
    // -------------------------------------------------------------------------

    private function publishConfig(): void
    {
        info('Publishing configuration...');

        $configPath = config_path('shell-gate.php');

        if (file_exists($configPath) && ! $this->option('force')) {
            $this->components->twoColumnDetail('config/shell-gate.php', '<fg=yellow>already exists (use --force to overwrite)</>');

            return;
        }

        $this->callSilently('vendor:publish', [
            '--tag' => 'shell-gate-config',
            '--force' => $this->option('force'),
        ]);

        $this->components->twoColumnDetail('config/shell-gate.php', '<fg=green>published</>');
    }

    // -------------------------------------------------------------------------
    // Step 2: Migrations
    // -------------------------------------------------------------------------

    private function runMigrations(): void
    {
        info('Running migrations...');

        $this->callSilently('migrate', [
            '--force' => app()->environment('production'),
        ]);

        $this->components->twoColumnDetail('terminal_sessions table', '<fg=green>migrated</>');
    }

    // -------------------------------------------------------------------------
    // Step 3: Patch AdminPanelProvider
    // -------------------------------------------------------------------------

    private function patchAdminPanelProvider(): void
    {
        info('Registering plugin in Filament panel...');

        $providerPath = $this->findPanelProviderPath();

        if ($providerPath === null) {
            warning('Could not find a Filament PanelProvider file.');
            $this->printManualPluginInstructions();

            return;
        }

        $contents = file_get_contents($providerPath);

        if ($contents === false) {
            warning("Could not read {$providerPath}.");
            $this->printManualPluginInstructions();

            return;
        }

        // Check if already registered
        if (str_contains($contents, 'ShellGatePlugin')) {
            $this->components->twoColumnDetail(
                basename($providerPath),
                '<fg=yellow>ShellGatePlugin already registered</>'
            );

            return;
        }

        // Add use statement
        $contents = $this->addUseStatement($contents);

        // Add plugin registration
        $contents = $this->addPluginRegistration($contents);

        if (file_put_contents($providerPath, $contents) !== false) {
            $this->components->twoColumnDetail(
                basename($providerPath),
                '<fg=green>ShellGatePlugin registered</>'
            );
        } else {
            warning("Could not write to {$providerPath}.");
            $this->printManualPluginInstructions();
        }
    }

    /**
     * Find the Filament PanelProvider file.
     */
    private function findPanelProviderPath(): ?string
    {
        // Check common locations
        $candidates = [
            app_path('Providers/Filament/AdminPanelProvider.php'),
            app_path('Providers/Filament/AppPanelProvider.php'),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // Search for any *PanelProvider.php in Filament directory
        $filamentDir = app_path('Providers/Filament');
        if (is_dir($filamentDir)) {
            $files = glob($filamentDir . '/*PanelProvider.php');
            if (! empty($files)) {
                return $files[0];
            }
        }

        return null;
    }

    /**
     * Add `use OctadecimalHQ\ShellGate\ShellGatePlugin;` import.
     */
    private function addUseStatement(string $contents): string
    {
        $useStatement = 'use OctadecimalHQ\\ShellGate\\ShellGatePlugin;';

        // Find the last `use` statement and add after it
        if (preg_match('/^use\s+[^;]+;/m', $contents)) {
            // Find position after the last `use ...;` line
            $lastUsePos = 0;
            if (preg_match_all('/^use\s+[^;]+;\n/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                $lastMatch = end($matches[0]);
                $lastUsePos = $lastMatch[1] + strlen($lastMatch[0]);
            }

            if ($lastUsePos > 0) {
                return substr($contents, 0, $lastUsePos)
                    . $useStatement . "\n"
                    . substr($contents, $lastUsePos);
            }
        }

        // Fallback: add after namespace declaration
        if (preg_match('/^namespace\s+[^;]+;\n/m', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $insertPos = $match[0][1] + strlen($match[0][0]);

            return substr($contents, 0, $insertPos)
                . "\n" . $useStatement . "\n"
                . substr($contents, $insertPos);
        }

        return $contents;
    }

    /**
     * Add ShellGatePlugin::make() to the panel configuration.
     */
    private function addPluginRegistration(string $contents): string
    {
        $pluginCode = 'ShellGatePlugin::make()';

        // Strategy 1: Find ->plugins([...]) and add to the array
        if (preg_match('/->plugins\(\[\s*/s', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $insertPos = $match[0][1] + strlen($match[0][0]);

            return substr($contents, 0, $insertPos)
                . "\n            {$pluginCode},\n"
                . substr($contents, $insertPos);
        }

        // Strategy 2: Find ->plugin( calls and add another ->plugin() after the last one
        if (preg_match_all('/->plugin\([^)]+\)/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            $lastMatch = end($matches[0]);
            $insertPos = $lastMatch[1] + strlen($lastMatch[0]);

            return substr($contents, 0, $insertPos)
                . "\n            ->plugin({$pluginCode})"
                . substr($contents, $insertPos);
        }

        // Strategy 3: Find ->pages( or ->widgets( or ->resources( and add ->plugin() before it
        if (preg_match('/(\s+)->(pages|widgets|resources|middleware|authMiddleware)\(/s', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $insertPos = $match[0][1];
            $indent = $match[1][0];

            return substr($contents, 0, $insertPos)
                . $indent . "->plugin({$pluginCode})"
                . substr($contents, $insertPos);
        }

        // Strategy 4: Find the return $panel line and add ->plugin() before the semicolon
        if (preg_match('/return\s+\$panel\b/s', $contents)) {
            // Find the last semicolon in the panel() method
            if (preg_match('/function\s+panel\s*\([^)]*\)[^{]*\{(.+)\}/s', $contents, $methodMatch, PREG_OFFSET_CAPTURE)) {
                $methodBody = $methodMatch[1][0];
                $methodBodyStart = $methodMatch[1][1];

                // Find the last semicolon
                $lastSemicolon = strrpos($methodBody, ';');
                if ($lastSemicolon !== false) {
                    $insertPos = $methodBodyStart + $lastSemicolon;

                    return substr($contents, 0, $insertPos)
                        . "\n            ->plugin({$pluginCode})"
                        . substr($contents, $insertPos);
                }
            }
        }

        // Fallback: could not patch automatically
        return $contents;
    }

    /**
     * Print manual instructions if auto-patch fails.
     */
    private function printManualPluginInstructions(): void
    {
        note('Add the plugin manually to your AdminPanelProvider.php:');
        $this->line('');
        $this->line('  <fg=cyan>use OctadecimalHQ\ShellGate\ShellGatePlugin;</>');
        $this->line('');
        $this->line('  ->plugin(ShellGatePlugin::make())');
        $this->line('');
    }

    // -------------------------------------------------------------------------
    // Step 4: Configure .env
    // -------------------------------------------------------------------------

    private function configureEnv(): void
    {
        info('Configuring environment...');

        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            warning('.env file not found — skipping.');

            return;
        }

        $envContent = file_get_contents($envPath);
        $changed = false;

        // Add gateway URL
        if (! str_contains($envContent, 'SHELL_GATE_GATEWAY_URL')) {
            $envContent .= "\n# Shell Gate\nSHELL_GATE_GATEWAY_URL=ws://127.0.0.1:7681\n";
            $changed = true;
            $this->components->twoColumnDetail('SHELL_GATE_GATEWAY_URL', '<fg=green>added to .env</>');
        } else {
            $this->components->twoColumnDetail('SHELL_GATE_GATEWAY_URL', '<fg=yellow>already in .env</>');
        }

        if ($changed) {
            file_put_contents($envPath, $envContent);
        }
    }

    // -------------------------------------------------------------------------
    // Step 5: Setup Gateway
    // -------------------------------------------------------------------------

    private function setupGateway(): void
    {
        info('Setting up terminal gateway...');

        $gatewayDir = $this->resolveGatewayPath();

        if ($gatewayDir === null) {
            warning('Gateway directory not found — skipping.');

            return;
        }

        // Create .env from .env.example
        $this->createGatewayEnv($gatewayDir);

        // Check Node.js
        if (! $this->checkNodeJs()) {
            warning('Node.js >= 18 is required for the gateway. Install it and run: npm install');
            warning("Gateway path: {$gatewayDir}");

            return;
        }

        // npm install
        $this->installGatewayDeps($gatewayDir);
    }

    /**
     * Resolve the gateway directory path.
     */
    private function resolveGatewayPath(): ?string
    {
        $candidates = [
            base_path('vendor/octadecimalhq/shellgate/gateway'),
            // Path repo: check composer.json for actual path
            $this->resolvePathRepoGateway(),
        ];

        foreach (array_filter($candidates) as $path) {
            if (is_dir($path) && file_exists($path . '/package.json')) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Try to resolve gateway path from composer path repository.
     */
    private function resolvePathRepoGateway(): ?string
    {
        $composerJson = base_path('composer.json');
        if (! file_exists($composerJson)) {
            return null;
        }

        $composer = json_decode(file_get_contents($composerJson), true);
        $repositories = $composer['repositories'] ?? [];

        foreach ($repositories as $repo) {
            if (($repo['type'] ?? '') === 'path') {
                $url = $repo['url'] ?? '';
                if (str_contains($url, 'shellgate') || str_contains($url, 'shell-gate')) {
                    $fullPath = Str::startsWith($url, '/') ? $url : base_path($url);
                    $gatewayPath = rtrim($fullPath, '/') . '/gateway';
                    if (is_dir($gatewayPath)) {
                        return $gatewayPath;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Create gateway .env from .env.example with auto-configured values.
     */
    private function createGatewayEnv(string $gatewayDir): void
    {
        $envPath = $gatewayDir . '/.env';
        $examplePath = $gatewayDir . '/.env.example';

        if (file_exists($envPath) && ! $this->option('force')) {
            $this->components->twoColumnDetail('gateway/.env', '<fg=yellow>already exists</>');

            return;
        }

        if (! file_exists($examplePath)) {
            return;
        }

        $envContent = file_get_contents($examplePath);

        // Set JWT_SECRET from Laravel APP_KEY
        $appKey = config('app.key', '');
        if (! empty($appKey)) {
            $envContent = preg_replace(
                '/^JWT_SECRET=.*$/m',
                'JWT_SECRET=' . $appKey,
                $envContent
            );
        }

        // Set DEFAULT_CWD to Laravel project root
        $envContent = preg_replace(
            '/^DEFAULT_CWD=.*$/m',
            'DEFAULT_CWD=' . base_path(),
            $envContent
        );

        file_put_contents($envPath, $envContent);

        $this->components->twoColumnDetail('gateway/.env', '<fg=green>created (JWT_SECRET + CWD configured)</>');
    }

    /**
     * Check if Node.js >= 18 is available.
     */
    private function checkNodeJs(): bool
    {
        $result = Process::run('node --version');

        if (! $result->successful()) {
            return false;
        }

        $version = trim($result->output());
        // Extract major version number from e.g. "v20.11.0"
        if (preg_match('/v?(\d+)/', $version, $matches)) {
            return (int) $matches[1] >= 18;
        }

        return false;
    }

    /**
     * Install gateway npm dependencies.
     */
    private function installGatewayDeps(string $gatewayDir): void
    {
        if (is_dir($gatewayDir . '/node_modules') && ! $this->option('force')) {
            $this->components->twoColumnDetail('gateway/node_modules', '<fg=yellow>already installed</>');

            return;
        }

        $this->components->twoColumnDetail('npm install', '<fg=cyan>installing...</>');

        $result = Process::path($gatewayDir)
            ->timeout(120)
            ->run('npm install --silent');

        if ($result->successful()) {
            $this->components->twoColumnDetail('gateway dependencies', '<fg=green>installed</>');
        } else {
            warning('npm install failed: ' . $result->errorOutput());
            note("Run manually: cd {$gatewayDir} && npm install");
        }
    }

    // -------------------------------------------------------------------------
    // UI Helpers
    // -------------------------------------------------------------------------

    private function printBanner(): void
    {
        $this->newLine();
        $this->line(' <fg=cyan;options=bold>Shell Gate Installer</>');
        $this->line(' <fg=gray>Real bash terminal in your Filament admin panel</>');
        $this->newLine();
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->components->info('Shell Gate installed successfully!');
        $this->newLine();

        $this->line(' <fg=yellow;options=bold>Next steps:</>');
        $this->newLine();
        $this->line('  1. Start the terminal gateway:');
        $this->line('     <fg=cyan>php artisan shellgate:serve</>');
        $this->newLine();
        $this->line('  2. Visit <fg=cyan>/admin/terminal</> in your browser');
        $this->newLine();

        if (! $this->option('dev') && app()->environment('production')) {
            $this->line('  3. Add your license key to .env:');
            $this->line('     <fg=cyan>SHELL_GATE_LICENSE_KEY=your-key-here</>');
            $this->newLine();
        }
    }
}
