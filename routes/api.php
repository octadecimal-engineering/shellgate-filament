<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Octadecimal\ShellGate\Http\Controllers\TerminalTokenController;
use Octadecimal\ShellGate\Http\Controllers\TerminalSessionController;
use Octadecimal\ShellGate\Http\Middleware\EnsureTerminalAccess;

/*
|--------------------------------------------------------------------------
| Shell Gate API Routes
|--------------------------------------------------------------------------
|
| API routes for Shell Gate. Require user authentication and terminal
| access authorization.
|
*/

Route::middleware(['web', 'auth'])
    ->prefix('api/terminal')
    ->name('shell-gate.')
    ->group(function () {
        // Get JWT token for WebSocket connection
        Route::post('/token', [TerminalTokenController::class, 'generate'])
            ->middleware(EnsureTerminalAccess::class)
            ->name('token.generate');

        // Check active sessions status
        Route::get('/sessions/active', [TerminalSessionController::class, 'active'])
            ->middleware(EnsureTerminalAccess::class)
            ->name('sessions.active');

        // End session (callback from gateway or manual)
        Route::post('/sessions/{sessionId}/end', [TerminalSessionController::class, 'end'])
            ->name('sessions.end');

        // Validate token (for gateway)
        Route::post('/token/validate', [TerminalTokenController::class, 'validate'])
            ->name('token.validate');
    });
