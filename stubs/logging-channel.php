<?php

/**
 * Shell Gate Audit Log Channel
 *
 * Add this configuration to your config/logging.php channels array:
 *
 * 'channels' => [
 *     // ... existing channels ...
 *
 *     'shell-gate-audit' => [
 *         'driver' => 'daily',
 *         'path' => storage_path('logs/shell-gate-audit.log'),
 *         'level' => env('SHELL_GATE_LOG_LEVEL', 'info'),
 *         'days' => env('SHELL_GATE_LOG_DAYS', 30),
 *     ],
 * ],
 */

return [
    'shell-gate-audit' => [
        'driver' => 'daily',
        'path' => storage_path('logs/shell-gate-audit.log'),
        'level' => env('SHELL_GATE_LOG_LEVEL', 'info'),
        'days' => env('SHELL_GATE_LOG_DAYS', 30),
        'permission' => 0644,
    ],
];
