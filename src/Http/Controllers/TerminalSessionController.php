<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Octadecimal\ShellGate\Models\TerminalSession;
use Octadecimal\ShellGate\Services\AuditService;

/**
 * Controller for terminal session management.
 */
class TerminalSessionController extends Controller
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Get active sessions for current user.
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $sessions = TerminalSession::forUser($user->id)
            ->active()
            ->orderByDesc('started_at')
            ->get([
                'id',
                'session_id',
                'started_at',
                'ip_address',
            ]);

        return response()->json([
            'sessions' => $sessions,
            'count' => $sessions->count(),
            'max_sessions' => config('shell-gate.limits.max_sessions_per_user', 3),
        ]);
    }

    /**
     * End a terminal session.
     *
     * Can be called by:
     * - Gateway callback when session ends
     * - User manually ending session
     * - Admin ending another user's session
     */
    public function end(Request $request, string $sessionId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:50',
            'gateway_secret' => 'nullable|string',
        ]);

        $session = TerminalSession::where('session_id', $sessionId)->first();

        if (! $session) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        // Check authorization
        if (! $this->canEndSession($request, $session)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (! $session->isActive()) {
            return response()->json([
                'message' => 'Session already ended',
                'ended_at' => $session->ended_at,
            ]);
        }

        $reason = $request->input('reason', 'user');
        $session->end($reason);

        // Log session end
        $this->auditService->logSessionEnd($session, $reason);

        return response()->json([
            'message' => 'Session ended',
            'session_id' => $sessionId,
            'ended_at' => $session->ended_at,
            'reason' => $reason,
            'duration_seconds' => $session->getDurationInSeconds(),
        ]);
    }

    /**
     * Check if request is authorized to end session.
     */
    private function canEndSession(Request $request, TerminalSession $session): bool
    {
        // Gateway callback with shared secret
        $gatewaySecret = $request->input('gateway_secret');
        $configSecret = config('shell-gate.auth.jwt_secret') ?: config('app.key');

        if ($gatewaySecret && hash_equals($configSecret, $gatewaySecret)) {
            return true;
        }

        // User ending their own session
        $user = $request->user();
        if ($user && $session->user_id === $user->id) {
            return true;
        }

        // Admin/super_admin can end any session
        if ($user && method_exists($user, 'hasRole')) {
            /** @phpstan-ignore-next-line */
            if ($user->hasRole(['super_admin', 'admin'])) {
                return true;
            }
        }

        // Check is_super_admin attribute
        if ($user && ($user->is_super_admin ?? false)) {
            return true;
        }

        return false;
    }
}
