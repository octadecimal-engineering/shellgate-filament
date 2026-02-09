# Configuration Guide

Complete reference for all configuration options in Shell Gate.

---

## Table of Contents

1. [Configuration File](#configuration-file)
2. [Environment Variables](#environment-variables)
3. [Plugin Options](#plugin-options)
4. [Gateway Configuration](#gateway-configuration)
5. [UI Customization](#ui-customization)
6. [Security Settings](#security-settings)

---

## Configuration File

After publishing, the configuration is located at `config/shell-gate.php`.

### Full Configuration Reference

```php
<?php

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
    | 2. ANYSTACK_CUSTOMER_API_KEY - Runtime API key (from installation docs)
    |
    | The runtime API key has minimal permissions (validate/activate only)
    | and is safe to use in your application.
    |
    | Purchase via Anystack: https://anystack.sh
    | License types: Single Site ($99), Unlimited ($299), Agency ($499)
    |
    */
    'license' => [
        // Your license key from purchase confirmation (required for production)
        // Accepted env vars: SHELL_GATE_LICENSE_KEY, shellgate-license-key
        'key' => env('SHELL_GATE_LICENSE_KEY') ?? env('shellgate-license-key'),

        // Enable/disable license verification (auto-disabled in local/testing)
        'verify' => env('SHELL_GATE_LICENSE_VERIFY', true),

        // Anystack runtime API configuration
        'anystack' => [
            // Customer runtime API key (license:validate, license:activate only)
            // Accepted env vars: ANYSTACK_CUSTOMER_API_KEY, shellgate-customer-runtime-api-key, anystack-api-key
            'api_key' => env('ANYSTACK_CUSTOMER_API_KEY')
                ?? env('shellgate-customer-runtime-api-key')
                ?? env('anystack-api-key'),

            // Product ID (public, safe to hardcode)
            'product_id' => 'a108fd3f-8389-40f5-beac-a1767ba70724',
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
```

---

## Environment Variables

All environment variables with their defaults:

```env
# License (required for production)
SHELL_GATE_LICENSE_KEY=your-license-key              # From purchase confirmation
ANYSTACK_CUSTOMER_API_KEY=8AE4QSBwTGwiPyrSvmw6vozlPbbkZr7J  # Runtime API key (same for all)
SHELL_GATE_LICENSE_VERIFY=true                       # Set false to disable

# Gateway
SHELL_GATE_GATEWAY_URL=wss://yourdomain.com/ws/terminal
SHELL_GATE_GATEWAY_HOST=127.0.0.1
SHELL_GATE_GATEWAY_PORT=7681

# Authentication
SHELL_GATE_JWT_SECRET=          # Defaults to APP_KEY
SHELL_GATE_TOKEN_TTL=300        # 5 minutes
SHELL_GATE_BIND_IP=true
SHELL_GATE_BIND_USER_AGENT=true

# Terminal
SHELL_GATE_SHELL=/bin/bash
SHELL_GATE_CWD=/var/www/app
SHELL_GATE_COLS=120             # Terminal columns
SHELL_GATE_ROWS=30              # Terminal rows

# Limits
SHELL_GATE_MAX_SESSIONS=10     # Per user
SHELL_GATE_IDLE_TIMEOUT=1800   # 30 minutes
SHELL_GATE_SESSION_TIMEOUT=0   # No limit (0)
SHELL_GATE_TOKEN_RATE_LIMIT=5  # Requests per minute

# Audit
SHELL_GATE_AUDIT_ENABLED=true
SHELL_GATE_AUDIT_CHANNEL=shell-gate-audit
SHELL_GATE_LOG_COMMANDS=false

# UI
SHELL_GATE_FONT_SIZE=14
SHELL_GATE_FONT_FAMILY="JetBrains Mono, Menlo, Monaco, monospace"
SHELL_GATE_HEIGHT=600px
SHELL_GATE_THEME=dark

# Filament Navigation
SHELL_GATE_NAV_GROUP=System
SHELL_GATE_NAV_LABEL=ShellGate
SHELL_GATE_NAV_SORT=100
SHELL_GATE_HIDE_NAV=false
```

### Alternative Env Var Names

For convenience, the following alternative env var names are also accepted:

| Standard | Alternative |
|----------|-------------|
| `SHELL_GATE_LICENSE_KEY` | `shellgate-license-key` |
| `ANYSTACK_CUSTOMER_API_KEY` | `shellgate-customer-runtime-api-key` or `anystack-api-key` |

---

## Plugin Options

Configure the plugin when registering in `AdminPanelProvider`:

```php
use OctadecimalHQ\ShellGate\ShellGatePlugin;

->plugin(
    ShellGatePlugin::make()

        // Authorization callback
        ->authorize(fn () => auth()->user()?->is_super_admin)

        // Navigation settings
        ->navigationGroup('System')
        ->navigationLabel('Terminal')
        ->navigationIcon('heroicon-o-command-line')
        ->navigationSort(100)

        // Override gateway URL
        ->gatewayUrl('wss://custom-domain.com/ws/terminal')

        // Hide from navigation (still accessible via direct URL)
        ->hideFromNavigation()
)
```

### Fluent API Reference

| Method | Type | Description |
|--------|------|-------------|
| `authorize(Closure\|bool\|null $callback)` | Closure/bool | Authorization check |
| `navigationGroup(?string $group)` | string | Filament nav group |
| `navigationLabel(?string $label)` | string | Nav menu label |
| `navigationIcon(?string $icon)` | string | Heroicon name |
| `navigationSort(?int $sort)` | int | Sort order |
| `gatewayUrl(?string $url)` | string | WebSocket URL |
| `hideFromNavigation(bool $hide = true)` | bool | Hide from sidebar |

### Default Authorization

When no `->authorize()` callback is set:

| Environment | Behavior |
|-------------|----------|
| `local` / `testing` | Any authenticated user has access |
| `production` | Requires `is_super_admin` attribute or Spatie `super_admin` role |

---

## Gateway Configuration

The gateway reads configuration from environment variables:

### Gateway Environment Variables

```env
# Server
PORT=7681
HOST=0.0.0.0

# Security
JWT_SECRET=your-laravel-app-key
ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# Terminal
TERMINAL_SHELL=/bin/bash
TERMINAL_CWD=/var/www/app
TERMINAL_ENV=TERM=xterm-256color,LANG=en_US.UTF-8

# Limits
MAX_SESSIONS=50
SESSION_TIMEOUT=3600
IDLE_TIMEOUT=900

# Logging
LOG_LEVEL=info                    # debug, info, warn, error
AUDIT_LOG_PATH=/var/log/terminal-audit.log
LOG_COMMANDS=false
```

---

## UI Customization

### Theme

The `theme` config option accepts a string preset name:

```php
// config/shell-gate.php
'ui' => [
    'theme' => 'dark',   // default
    // 'theme' => 'light',
],
```

Or set `theme` to `custom` and define colors:

```php
'ui' => [
    'theme' => 'custom',
    'colors' => [
        'background' => '#282a36',
        'foreground' => '#f8f8f2',
        'cursor' => '#f8f8f2',
        'selection' => 'rgba(68, 71, 90, 0.5)',
    ],
],
```

### Custom Fonts

```php
'ui' => [
    'font_family' => "'Fira Code', 'JetBrains Mono', monospace",
    'font_size' => 14,
],
```

Add font in your layout:

```html
<link href="https://fonts.googleapis.com/css2?family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
```

---

## Security Settings

### Strict Mode

For high-security environments:

```php
'auth' => [
    'token_ttl' => 120,           // 2 minutes only
    'bind_ip' => true,
    'bind_user_agent' => true,
],

'limits' => [
    'max_sessions_per_user' => 1,
    'session_timeout' => 1800,    // 30 minutes max
    'idle_timeout' => 300,        // 5 minutes idle
],

'audit' => [
    'enabled' => true,
    'log_commands' => true,       // Log all commands
],
```

### Allowed Commands (Gateway)

Restrict available commands in the gateway:

```javascript
// gateway.config.js
module.exports = {
    security: {
        // Command whitelist (regex patterns)
        allowedCommands: [
            /^php\s+artisan/,
            /^composer\s/,
            /^npm\s/,
            /^docker\s+(ps|logs|compose)/,
            /^ls\b/,
            /^cd\b/,
            /^cat\b/,
            /^grep\b/,
        ],

        // Command blacklist (checked first)
        blockedCommands: [
            /rm\s+-rf\s+\//,
            /mkfs/,
            /dd\s+if=/,
            />\/dev\//,
        ],
    },
};
```

---

## References

- [xterm.js Options](https://xtermjs.org/docs/api/terminal/interfaces/iterminaloptions/)
- [xterm.js Theme](https://xtermjs.org/docs/api/terminal/interfaces/itheme/)
- [Filament Configuration](https://filamentphp.com/docs/5.x/panels/configuration)
