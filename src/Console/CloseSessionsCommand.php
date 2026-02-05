<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate\Console;

use Octadecimal\ShellGate\Models\TerminalSession;

class CloseSessionsCommand extends \Illuminate\Console\Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shell-gate:close-sessions
                            {--reason=artisan : Reason for closing (e.g. artisan, maintenance)}
                            {--user= : Only close sessions for this user ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Close all active Shell Gate terminal sessions (or for a specific user)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $reason = (string) $this->option('reason');
        $userId = $this->option('user');

        $query = TerminalSession::active();

        if ($userId !== null && $userId !== '') {
            $query->forUser((int) $userId);
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No active terminal sessions to close.');

            return self::SUCCESS;
        }

        $updated = 0;
        foreach ($query->get() as $session) {
            if ($session->end($reason)) {
                $updated++;
            }
        }

        $this->info("Closed {$updated} terminal session(s). Reason: {$reason}.");

        return self::SUCCESS;
    }
}
