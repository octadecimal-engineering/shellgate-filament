<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate\Services;

use Illuminate\Support\Facades\Log;
use Octadecimal\ShellGate\Models\TerminalSession;

/**
 * Service for audit logging of terminal sessions.
 */
class AuditService
{
    /**
     * @param array<string> $redactPatterns
     */
    public function __construct(
        private readonly string $channel = 'shell-gate-audit',
        private readonly bool $enabled = true,
        private readonly array $redactPatterns = [],
    ) {}

    /**
     * Log session start event.
     */
    public function logSessionStart(TerminalSession $session): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->log('info', 'Terminal session started', [
            'session_id' => $session->session_id,
            'user_id' => $session->user_id,
            'ip_address' => $session->ip_address,
            'user_agent' => $this->truncateUserAgent($session->user_agent),
            'started_at' => $session->started_at?->toIso8601String(),
        ]);
    }

    /**
     * Log session end event.
     */
    public function logSessionEnd(TerminalSession $session, string $reason = 'unknown'): void
    {
        if (! $this->enabled) {
            return;
        }

        $duration = null;
        if ($session->started_at && $session->ended_at) {
            $duration = $session->started_at->diffInSeconds($session->ended_at);
        }

        $this->log('info', 'Terminal session ended', [
            'session_id' => $session->session_id,
            'user_id' => $session->user_id,
            'reason' => $reason,
            'duration_seconds' => $duration,
            'ended_at' => $session->ended_at?->toIso8601String(),
        ]);
    }

    /**
     * Log command execution (optional feature).
     */
    public function logCommand(TerminalSession $session, string $command): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->log('debug', 'Terminal command', [
            'session_id' => $session->session_id,
            'user_id' => $session->user_id,
            'command' => $this->redact($command),
        ]);
    }

    /**
     * Log security event (failed auth, rate limit, etc).
     *
     * @param array<string, mixed> $context
     */
    public function logSecurityEvent(string $event, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->log('warning', "Security event: {$event}", $context);
    }

    /**
     * Redact sensitive information from command.
     */
    public function redact(string $text): string
    {
        $patterns = $this->redactPatterns ?: config('shell-gate.audit.redact_patterns', []);

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, '[REDACTED]', $text) ?? $text;
        }

        return $text;
    }

    /**
     * Write to log channel.
     * Falls back to default Laravel log if configured channel doesn't exist.
     *
     * @param array<string, mixed> $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel($this->channel)->$level("[ShellGate] {$message}", $context);
        } catch (\InvalidArgumentException $e) {
            // Channel not configured, fallback to default log
            Log::$level("[ShellGate] {$message}", $context);
        }
    }

    /**
     * Truncate User-Agent for logging.
     */
    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null) {
            return null;
        }

        return strlen($userAgent) > 100
            ? substr($userAgent, 0, 100) . '...'
            : $userAgent;
    }
}
