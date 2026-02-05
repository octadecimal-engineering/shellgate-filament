<?php

declare(strict_types=1);

namespace Octadecimal\ShellGate\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Octadecimal\ShellGate\Services\AuditService;
use Octadecimal\ShellGate\ShellGatePlugin;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure user has access to terminal.
 */
class EnsureTerminalAccess
{
    public function __construct(
        private readonly AuditService $auditService,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (! $request->user()) {
            return $this->unauthorized($request, 'Authentication required');
        }

        // Check plugin authorization
        if (! $this->isAuthorized()) {
            $this->auditService->logSecurityEvent('access_denied', [
                'user_id' => $request->user()->id,
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            return $this->forbidden($request, 'Access denied');
        }

        return $next($request);
    }

    /**
     * Check if user is authorized via plugin callback.
     */
    private function isAuthorized(): bool
    {
        try {
            return ShellGatePlugin::get()->isAuthorized();
        } catch (\Exception) {
            // Plugin not registered, check default authorization
            return $this->defaultAuthorization();
        }
    }

    /**
     * Default authorization check when plugin is not available.
     */
    private function defaultAuthorization(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        // Check for super_admin attribute
        if ($user->is_super_admin ?? false) {
            return true;
        }

        // Check for role method (Spatie Permission)
        if (method_exists($user, 'hasRole')) {
            /** @phpstan-ignore-next-line */
            return $user->hasRole('super_admin');
        }

        return false;
    }

    /**
     * Return unauthorized response.
     */
    private function unauthorized(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 401);
        }

        return redirect()->guest(route('login'));
    }

    /**
     * Return forbidden response.
     */
    private function forbidden(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => $message], 403);
        }

        abort(403, $message);
    }
}
