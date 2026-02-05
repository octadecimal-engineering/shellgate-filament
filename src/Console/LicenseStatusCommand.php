<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate\Console;

use Illuminate\Console\Command;
use Octadecimal\ShellGate\Services\LicenseService;

/**
 * Artisan command to check and manage Shell Gate license.
 */
class LicenseStatusCommand extends Command
{
    protected $signature = 'shell-gate:license
                            {--refresh : Clear cached license status and re-validate}';

    protected $description = 'Check Shell Gate license status';

    public function handle(LicenseService $licenseService): int
    {
        if ($this->option('refresh')) {
            $this->info('Clearing license cache...');
            $licenseService->clearCache();
        }

        $this->info('Checking license status...');
        $this->newLine();

        $result = $licenseService->validate();

        // Display status
        $statusColor = $result['valid'] ? 'green' : 'red';
        $statusIcon = $result['valid'] ? '✓' : '✗';

        $this->line("  Status: <fg={$statusColor}>{$statusIcon} {$result['status']}</>");
        $this->line("  Message: {$result['message']}");
        $this->line('  Cached: ' . ($result['cached'] ? 'Yes' : 'No (fresh validation)'));

        if (isset($result['expires_at'])) {
            $this->line("  Expires: {$result['expires_at']}");
        }

        if (isset($result['license_id'])) {
            $this->line("  License ID: {$result['license_id']}");
        }

        $this->newLine();

        // Show configuration info
        $this->line('<fg=gray>Configuration:</>');
        $this->line('  License key: ' . (config('shell-gate.license.key') ? '***' . substr(config('shell-gate.license.key'), -8) : '<fg=yellow>Not set</>'));
        $this->line('  Verification: ' . (config('shell-gate.license.verify') ? 'Enabled' : '<fg=yellow>Disabled</>'));
        $this->line('  Environment: ' . app()->environment());

        if (app()->environment(['local', 'testing'])) {
            $this->newLine();
            $this->warn('Note: License verification is skipped in local/testing environments.');
        }

        return $result['valid'] ? self::SUCCESS : self::FAILURE;
    }
}
