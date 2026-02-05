<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | License Configuration
    |--------------------------------------------------------------------------
    |
    | Shell Gate uses Anystack for license verification.
    |
    | REQUIRED for production:
    | 1. SHELL_GATE_LICENSE_KEY - Your license key (from purchase confirmation)
    | 2. ANYSTACK_CUSTOMER_API_KEY - Runtime API key (from octadecimal.engineering docs)
    |
    | The runtime API key has minimal permissions (validate/activate only)
    | and is safe to use in your application.
    |
    | Purchase: https://octadecimal.engineering/shell-gate
    | License types: Single Site ($99), Unlimited ($299), Agency ($499)
    |
    */
    'license' => [
        // Your license key from purchase confirmation (required for production)
        'key' => env('SHELL_GATE_LICENSE_KEY'),

        // Enable/disable license verification (auto-disabled in local/testing)
        'verify' => env('SHELL_GATE_LICENSE_VERIFY', true),

        // Anystack runtime API configuration
        // The customer API key is provided in installation docs (minimal permissions)
        'anystack' => [
            // Customer runtime API key (license:validate, license:activate only)
            // Get this from: https://octadecimal.engineering/shell-gate/docs/installation
            'api_key' => env('ANYSTACK_CUSTOMER_API_KEY'),

            // Product ID (public, safe to hardcode)
            'product_id' => 'a100f72b-befe-48b4-a40c-ba1f62d626ff',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the terminal WebSocket gateway. The gateway is a Node.js
    | server that handles PTY sessions over WebSocket.
    |
    */
    'gateway' => [
        // WebSocket gateway URL visible to the browser
        'url' => env('SHELL_GATE_GATEWAY_URL', 'ws://localhost:7681'),

        // Host and port the gateway listens on (for server configuration)
        'host' => env('SHELL_GATE_GATEWAY_HOST', '127.0.0.1'),
        'port' => env('SHELL_GATE_GATEWAY_PORT', 7681),

        // Health check endpoint
        'health_path' => '/health',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | JWT authentication configuration for terminal sessions.
    |
    */
    'auth' => [
        // JWT secret (defaults to APP_KEY)
        'jwt_secret' => env('SHELL_GATE_JWT_SECRET'),

        // Token TTL in seconds (default 5 minutes)
        'token_ttl' => env('SHELL_GATE_TOKEN_TTL', 300),

        // Bind token to client IP address
        'bind_ip' => env('SHELL_GATE_BIND_IP', true),

        // Bind token to client User-Agent
        'bind_user_agent' => env('SHELL_GATE_BIND_USER_AGENT', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminal Configuration
    |--------------------------------------------------------------------------
    |
    | Shell and PTY environment settings.
    |
    */
    'terminal' => [
        // Shell to execute
        'shell' => env('SHELL_GATE_SHELL', '/bin/bash'),

        // Working directory (null = user home)
        'cwd' => env('SHELL_GATE_CWD'),

        // Environment variables passed to shell
        'env' => [
            'TERM' => 'xterm-256color',
            'COLORTERM' => 'truecolor',
            'LANG' => 'en_US.UTF-8',
        ],

        // Default terminal dimensions (columns x rows)
        'cols' => env('SHELL_GATE_COLS', 120),
        'rows' => env('SHELL_GATE_ROWS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Limits Configuration
    |--------------------------------------------------------------------------
    |
    | Security limits for terminal sessions.
    |
    */
    'limits' => [
        // Maximum concurrent sessions per user
        'max_sessions_per_user' => env('SHELL_GATE_MAX_SESSIONS', 10),

        // Maximum session idle time (seconds, 0 = no limit)
        'idle_timeout' => env('SHELL_GATE_IDLE_TIMEOUT', 1800),

        // Maximum session duration (seconds, 0 = no limit)
        'session_timeout' => env('SHELL_GATE_SESSION_TIMEOUT', 0),

        // Token rate limiting (requests per minute per user)
        'token_rate_limit' => env('SHELL_GATE_TOKEN_RATE_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    |
    | Logging and audit configuration for terminal sessions.
    |
    */
    'audit' => [
        // Enable audit logging
        'enabled' => env('SHELL_GATE_AUDIT_ENABLED', true),

        // Laravel log channel (can create a dedicated channel)
        'channel' => env('SHELL_GATE_AUDIT_CHANNEL', 'shell-gate-audit'),

        // Log commands (requires gateway callback)
        'log_commands' => env('SHELL_GATE_LOG_COMMANDS', false),

        // Patterns for redaction (e.g., passwords, tokens)
        'redact_patterns' => [
            '/password\s*[=:]\s*\S+/i',
            '/token\s*[=:]\s*\S+/i',
            '/secret\s*[=:]\s*\S+/i',
            '/api[_-]?key\s*[=:]\s*\S+/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Terminal appearance configuration in the browser.
    |
    */
    'ui' => [
        // Font size (px)
        'font_size' => env('SHELL_GATE_FONT_SIZE', 14),

        // Font family
        'font_family' => env('SHELL_GATE_FONT_FAMILY', 'JetBrains Mono, Menlo, Monaco, monospace'),

        // Terminal container height
        'height' => env('SHELL_GATE_HEIGHT', '600px'),

        // Color theme (dark/light or custom)
        'theme' => env('SHELL_GATE_THEME', 'dark'),

        // Colors (if theme = custom)
        'colors' => [
            'background' => '#1e1e2e',
            'foreground' => '#cdd6f4',
            'cursor' => '#f5e0dc',
            'selection' => '#45475a',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Filament panel integration.
    |
    */
    'filament' => [
        // Terminal page path (relative to panel)
        'path' => 'terminal',

        // Navigation group (null = no grouping)
        'navigation_group' => env('SHELL_GATE_NAV_GROUP', 'System'),

        // Navigation label (shown in sidebar and as page title inside the terminal)
        'navigation_label' => env('SHELL_GATE_NAV_LABEL', 'ShellGate'),

        // Navigation icon
        'navigation_icon' => 'heroicon-o-command-line',

        // Navigation sort order
        'navigation_sort' => env('SHELL_GATE_NAV_SORT', 100),

        // Hide from navigation (page accessible directly via URL)
        'hide_from_navigation' => env('SHELL_GATE_HIDE_NAV', false),
    ],
];
