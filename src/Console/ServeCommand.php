<?php

declare(strict_types=1);

namespace OctadecimalHQ\ShellGate\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process as SymfonyProcess;

/**
 * Start the Shell Gate terminal gateway (Node.js WebSocket server).
 *
 * Handles auto-detection of gateway path, npm install, .env configuration,
 * and runs the gateway process in foreground with output streaming.
 */
class ServeCommand extends Command
{
    protected $signature = 'shellgate:serve
                            {--port=7681 : Port to listen on}
                            {--host=127.0.0.1 : Host to bind to}
                            {--install : Force npm install before starting}';

    protected $description = 'Start the Shell Gate terminal gateway';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $gatewayDir = $this->resolveGatewayPath();

        if ($gatewayDir === null) {
            $this->components->error('Gateway directory not found.');
            $this->line('  Run <fg=cyan>php artisan shellgate:install</> first.');

            return self::FAILURE;
        }

        // Check Node.js
        if (! $this->checkNodeJs()) {
            $this->components->error('Node.js >= 18 is required but was not found.');
            $this->line('  Install Node.js: <fg=cyan>https://nodejs.org</>');

            return self::FAILURE;
        }

        // Auto npm install if needed
        if ($this->option('install') || ! is_dir($gatewayDir . '/node_modules')) {
            $this->components->info('Installing gateway dependencies...');

            $npmResult = Process::path($gatewayDir)
                ->timeout(120)
                ->run('npm install');

            if (! $npmResult->successful()) {
                $this->components->error('npm install failed:');
                $this->line($npmResult->errorOutput());

                return self::FAILURE;
            }

            $this->components->info('Dependencies installed.');
        }

        // Create .env if missing
        $this->ensureGatewayEnv($gatewayDir);

        // Build environment
        $env = $this->buildGatewayEnv($gatewayDir);

        // Start gateway
        $port = $this->option('port');
        $host = $this->option('host');

        $this->newLine();
        $this->line(" <fg=cyan;options=bold>Shell Gate Gateway</>");
        $this->line(" <fg=gray>Listening on {$host}:{$port}</>");
        $this->line(" <fg=gray>Press Ctrl+C to stop</>");
        $this->newLine();

        // Run Node.js process in foreground with real-time output
        $process = Process::path($gatewayDir)
            ->env($env)
            ->timeout(0)  // No timeout — runs until killed
            ->forever()
            ->tty(SymfonyProcess::isTtySupported())
            ->run("node index.js", function (string $type, string $output): void {
                $this->output->write($output);
            });

        return $process->exitCode() ?? self::SUCCESS;
    }

    /**
     * Build environment variables for the gateway process.
     */
    private function buildGatewayEnv(string $gatewayDir): array
    {
        $env = [];

        // Override port/host from flags
        $env['PORT'] = (string) $this->option('port');
        $env['HOST'] = (string) $this->option('host');

        // JWT secret from Laravel config (if not in gateway .env)
        $gatewayEnvPath = $gatewayDir . '/.env';
        $gatewayEnvContent = file_exists($gatewayEnvPath) ? file_get_contents($gatewayEnvPath) : '';

        if (! str_contains($gatewayEnvContent, 'JWT_SECRET=')
            || str_contains($gatewayEnvContent, 'JWT_SECRET=your-secret-key-here')) {
            $appKey = config('app.key', '');
            if (! empty($appKey)) {
                $env['JWT_SECRET'] = $appKey;
            }
        }

        // Default CWD to Laravel project root
        if (! str_contains($gatewayEnvContent, 'DEFAULT_CWD=')
            || preg_match('/^DEFAULT_CWD=\s*$/m', $gatewayEnvContent)) {
            $env['DEFAULT_CWD'] = base_path();
        }

        return $env;
    }

    /**
     * Create gateway .env from .env.example if missing.
     */
    private function ensureGatewayEnv(string $gatewayDir): void
    {
        $envPath = $gatewayDir . '/.env';
        $examplePath = $gatewayDir . '/.env.example';

        if (file_exists($envPath)) {
            return;
        }

        if (! file_exists($examplePath)) {
            return;
        }

        $content = file_get_contents($examplePath);

        // Auto-configure JWT_SECRET
        $appKey = config('app.key', '');
        if (! empty($appKey)) {
            $content = preg_replace(
                '/^JWT_SECRET=.*$/m',
                'JWT_SECRET=' . $appKey,
                $content
            );
        }

        // Auto-configure DEFAULT_CWD
        $content = preg_replace(
            '/^DEFAULT_CWD=.*$/m',
            'DEFAULT_CWD=' . base_path(),
            $content
        );

        file_put_contents($envPath, $content);
    }

    /**
     * Resolve the gateway directory path.
     */
    private function resolveGatewayPath(): ?string
    {
        $candidates = [
            base_path('vendor/octadecimalhq/shellgate/gateway'),
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
     * Check if Node.js >= 18 is available.
     */
    private function checkNodeJs(): bool
    {
        $result = Process::run('node --version');

        if (! $result->successful()) {
            return false;
        }

        $version = trim($result->output());
        if (preg_match('/v?(\d+)/', $version, $matches)) {
            return (int) $matches[1] >= 18;
        }

        return false;
    }
}
