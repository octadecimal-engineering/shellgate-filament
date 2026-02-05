<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Octadecimal\ShellGate\Models\TerminalSession;
use Octadecimal\ShellGate\Services\AuditService;
use Octadecimal\ShellGate\Services\JwtService;
use Octadecimal\ShellGate\ShellGatePlugin;

/**
 * Controller for terminal token generation.
 */
class TerminalTokenController extends Controller
{
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly AuditService $auditService,
    ) {}

    /**
     * Generate a JWT token for terminal session.
     */
    public function generate(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Rate limiting
        $rateLimitKey = 'shell-gate-token:' . $user->id;
        $maxAttempts = config('shell-gate.limits.token_rate_limit', 5);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);

            $this->auditService->logSecurityEvent('rate_limit_exceeded', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'Too many requests',
                'retry_after' => $seconds,
            ], 429)->header('Retry-After', (string) $seconds);
        }

        RateLimiter::hit($rateLimitKey, 60);

        // Check session limit
        $maxSessions = config('shell-gate.limits.max_sessions_per_user', 3);
        $activeSessions = TerminalSession::forUser($user->id)->active()->count();

        if ($activeSessions >= $maxSessions) {
            $this->auditService->logSecurityEvent('session_limit_exceeded', [
                'user_id' => $user->id,
                'active_sessions' => $activeSessions,
                'max_sessions' => $maxSessions,
            ]);

            return response()->json([
                'error' => 'Maximum session limit reached',
                'active_sessions' => $activeSessions,
                'max_sessions' => $maxSessions,
            ], 429);
        }

        // Create session
        $sessionId = (string) Str::uuid();

        $session = TerminalSession::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'started_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Generate token
        $tokenData = $this->jwtService->generate(
            payload: [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'cwd' => config('shell-gate.terminal.cwd'),
            ],
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        );

        // Log session start
        $this->auditService->logSessionStart($session);

        // Get gateway URL
        $gatewayUrl = $this->getGatewayUrl();

        return response()->json([
            'token' => $tokenData['token'],
            'expires_at' => $tokenData['expires_at'],
            'session_id' => $sessionId,
            'gateway_url' => $gatewayUrl,
        ]);
    }

    /**
     * Validate a JWT token (for gateway use).
     */
    public function validate(Request $request): JsonResponse
    {
        $token = $request->input('token');

        if (! $token) {
            return response()->json(['valid' => false, 'error' => 'Token required'], 400);
        }

        try {
            $payload = $this->jwtService->validate(
                token: $token,
                ip: $request->input('ip'),
                userAgent: $request->input('user_agent'),
            );

            return response()->json([
                'valid' => true,
                'payload' => $payload,
            ]);
        } catch (\Exception $e) {
            $this->auditService->logSecurityEvent('token_validation_failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'valid' => false,
                'error' => $e->getMessage(),
            ], 401);
        }
    }

    /**
     * Get gateway URL from plugin or config.
     */
    private function getGatewayUrl(): string
    {
        try {
            return ShellGatePlugin::get()->getGatewayUrl();
        } catch (\Exception) {
            return config('shell-gate.gateway.url', 'ws://localhost:7681');
        }
    }
}
