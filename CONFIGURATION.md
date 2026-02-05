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
7. [Multi-tenancy](#multi-tenancy)

---

## Configuration File

After publishing, the configuration is located at `config/web-terminal.php`.

### Full Configuration Reference

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gateway Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the Terminal Gateway WebSocket server.
    |
    */
    'gateway' => [
        // WebSocket URL for browser connections (wss:// in production)
        'url' => env('WEB_TERMINAL_GATEWAY_URL', 'ws://localhost:7681'),
        
        // Gateway host and port (for local gateway process)
        'host' => env('WEB_TERMINAL_GATEWAY_HOST', '127.0.0.1'),
        'port' => env('WEB_TERMINAL_GATEWAY_PORT', 7681),
        
        // Health check endpoint
        'health_endpoint' => '/health',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | JWT token and session authentication settings.
    |
    */
    'auth' => [
        // JWT secret key (defaults to APP_KEY)
        'jwt_secret' => env('WEB_TERMINAL_JWT_SECRET'),
        
        // Token time-to-live in seconds (default: 5 minutes)
        'token_ttl' => env('WEB_TERMINAL_TOKEN_TTL', 300),
        
        // Bind token to client IP address
        'bind_ip' => env('WEB_TERMINAL_BIND_IP', true),
        
        // Bind token to user agent
        'bind_user_agent' => env('WEB_TERMINAL_BIND_UA', true),
        
        // JWT algorithm
        'algorithm' => 'HS256',
    ],

    /*
    |--------------------------------------------------------------------------
    | Terminal Settings
    |--------------------------------------------------------------------------
    |
    | Shell and PTY configuration.
    |
    */
    'terminal' => [
        // Shell to spawn (bash, zsh, sh)
        'shell' => env('WEB_TERMINAL_SHELL', '/bin/bash'),
        
        // Working directory for new sessions
        'cwd' => env('WEB_TERMINAL_CWD', base_path()),
        
        // Environment variables to pass to shell
        'env' => [
            'TERM' => 'xterm-256color',
            'LANG' => 'en_US.UTF-8',
            'LC_ALL' => 'en_US.UTF-8',
        ],
        
        // Additional environment from .env (comma-separated keys)
        'pass_env' => env('WEB_TERMINAL_PASS_ENV', ''),
        
        // Default terminal dimensions
        'cols' => 80,
        'rows' => 24,
        
        // System user to run shell as (null = same as gateway)
        'user' => env('WEB_TERMINAL_USER'),
        
        // Chroot path (null = no chroot)
        'chroot' => env('WEB_TERMINAL_CHROOT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limits to prevent abuse.
    |
    */
    'limits' => [
        // Maximum concurrent sessions per user
        'max_sessions_per_user' => env('WEB_TERMINAL_MAX_SESSIONS_USER', 2),
        
        // Maximum total concurrent sessions
        'max_total_sessions' => env('WEB_TERMINAL_MAX_SESSIONS_TOTAL', 50),
        
        // Token requests per minute per user
        'token_requests_per_minute' => 5,
        
        // Maximum connections per IP
        'max_connections_per_ip' => 3,
        
        // Session timeout in seconds (0 = no timeout)
        'session_timeout' => env('WEB_TERMINAL_SESSION_TIMEOUT', 3600),
        
        // Idle timeout in seconds (0 = no timeout)
        'idle_timeout' => env('WEB_TERMINAL_IDLE_TIMEOUT', 900),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logging
    |--------------------------------------------------------------------------
    |
    | Session and command logging settings.
    |
    */
    'audit' => [
        // Enable audit logging
        'enabled' => env('WEB_TERMINAL_AUDIT_ENABLED', true),
        
        // Log channel name
        'channel' => env('WEB_TERMINAL_AUDIT_CHANNEL', 'terminal-audit'),
        
        // Log all commands (high volume, security sensitive)
        'log_commands' => env('WEB_TERMINAL_LOG_COMMANDS', false),
        
        // Log terminal output (very high volume)
        'log_output' => env('WEB_TERMINAL_LOG_OUTPUT', false),
        
        // Patterns to redact from logs
        'redact_patterns' => [
            '/(password|secret|token|key|api_key|apikey)=[^\s]+/i',
            '/Bearer\s+[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+/i',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Configuration
    |--------------------------------------------------------------------------
    |
    | Terminal appearance and behavior in Filament.
    |
    */
    'ui' => [
        // Terminal font family
        'font_family' => "'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace",
        
        // Font size in pixels
        'font_size' => 14,
        
        // Line height multiplier
        'line_height' => 1.2,
        
        // Cursor style: 'block', 'underline', 'bar'
        'cursor_style' => 'block',
        
        // Cursor blink
        'cursor_blink' => true,
        
        // Terminal height in viewport units or pixels
        'height' => '70vh',
        
        // Enable WebGL renderer (better performance)
        'use_webgl' => true,
        
        // Theme (see UI Customization section)
        'theme' => [
            'background' => '#0d0d0d',
            'foreground' => '#d4d4d4',
            'cursor' => '#d4d4d4',
            'cursorAccent' => '#0d0d0d',
            'selection' => 'rgba(255, 255, 255, 0.3)',
            'black' => '#000000',
            'red' => '#cd3131',
            'green' => '#0dbc79',
            'yellow' => '#e5e510',
            'blue' => '#2472c8',
            'magenta' => '#bc3fbc',
            'cyan' => '#11a8cd',
            'white' => '#e5e5e5',
            'brightBlack' => '#666666',
            'brightRed' => '#f14c4c',
            'brightGreen' => '#23d18b',
            'brightYellow' => '#f5f543',
            'brightBlue' => '#3b8eea',
            'brightMagenta' => '#d670d6',
            'brightCyan' => '#29b8db',
            'brightWhite' => '#ffffff',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    |
    | Navigation and panel settings.
    |
    */
    'filament' => [
        // Navigation group
        'navigation_group' => 'System',
        
        // Navigation label
        'navigation_label' => 'Terminal',
        
        // Navigation icon
        'navigation_icon' => 'heroicon-o-command-line',
        
        // Navigation sort order
        'navigation_sort' => 100,
        
        // Page title
        'page_title' => 'Web Terminal',
        
        // Show in navigation (can be controlled dynamically)
        'show_in_navigation' => true,
    ],
];
```

---

## Environment Variables

All environment variables with their defaults:

```env
# Gateway
WEB_TERMINAL_GATEWAY_URL=wss://yourdomain.com/ws/terminal
WEB_TERMINAL_GATEWAY_HOST=127.0.0.1
WEB_TERMINAL_GATEWAY_PORT=7681

# Authentication
WEB_TERMINAL_JWT_SECRET=          # Defaults to APP_KEY
WEB_TERMINAL_TOKEN_TTL=300        # 5 minutes
WEB_TERMINAL_BIND_IP=true
WEB_TERMINAL_BIND_UA=true

# Terminal
WEB_TERMINAL_SHELL=/bin/bash
WEB_TERMINAL_CWD=/var/www/app
WEB_TERMINAL_USER=                # Run as specific user
WEB_TERMINAL_CHROOT=              # Chroot path
WEB_TERMINAL_PASS_ENV=            # Additional env vars

# Limits
WEB_TERMINAL_MAX_SESSIONS_USER=2
WEB_TERMINAL_MAX_SESSIONS_TOTAL=50
WEB_TERMINAL_SESSION_TIMEOUT=3600
WEB_TERMINAL_IDLE_TIMEOUT=900

# Audit
WEB_TERMINAL_AUDIT_ENABLED=true
WEB_TERMINAL_AUDIT_CHANNEL=terminal-audit
WEB_TERMINAL_LOG_COMMANDS=false
WEB_TERMINAL_LOG_OUTPUT=false
```

---

## Plugin Options

Configure the plugin when registering in `AdminPanelProvider`:

```php
use Octadecimal\ShellGate\ShellGatePlugin;

->plugin(
    ShellGatePlugin::make()
    
        // Authorization callback
        ->authorize(fn () => auth()->user()?->is_super_admin)
        
        // Or use a gate
        ->authorizeUsing('access-terminal')
        
        // Navigation settings
        ->navigationGroup('System')
        ->navigationLabel('Terminal')
        ->navigationIcon('heroicon-o-command-line')
        ->navigationSort(100)
        
        // Override gateway URL
        ->gatewayUrl('wss://custom-domain.com/ws/terminal')
        
        // Custom working directory
        ->workingDirectory('/var/www/custom')
        
        // UI customization
        ->fontSize(16)
        ->fontFamily('JetBrains Mono, monospace')
        ->height('80vh')
        
        // Disable WebGL for compatibility
        ->useWebgl(false)
        
        // Custom theme
        ->theme([
            'background' => '#1e1e1e',
            'foreground' => '#cccccc',
        ])
        
        // Session limits
        ->maxSessionsPerUser(1)
        ->sessionTimeout(1800)
        ->idleTimeout(600)
)
```

### Fluent API Reference

| Method | Type | Description |
|--------|------|-------------|
| `authorize(Closure $callback)` | Closure | Authorization check |
| `authorizeUsing(string $gate)` | string | Use Laravel Gate |
| `navigationGroup(string $group)` | string | Filament nav group |
| `navigationLabel(string $label)` | string | Nav menu label |
| `navigationIcon(string $icon)` | string | Heroicon name |
| `navigationSort(int $sort)` | int | Sort order |
| `gatewayUrl(string $url)` | string | WebSocket URL |
| `workingDirectory(string $path)` | string | Shell CWD |
| `fontSize(int $size)` | int | Font size in px |
| `fontFamily(string $font)` | string | CSS font-family |
| `height(string $height)` | string | CSS height |
| `useWebgl(bool $use)` | bool | Enable WebGL |
| `theme(array $colors)` | array | xterm.js theme |
| `maxSessionsPerUser(int $max)` | int | Per-user limit |
| `sessionTimeout(int $seconds)` | int | Max session length |
| `idleTimeout(int $seconds)` | int | Idle disconnect |

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
TERMINAL_USER=                    # Optional: run as user
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

### Gateway Configuration File

Alternatively, create `gateway.config.js`:

```javascript
module.exports = {
    server: {
        port: process.env.PORT || 7681,
        host: process.env.HOST || '0.0.0.0',
    },
    
    security: {
        jwtSecret: process.env.JWT_SECRET,
        allowedOrigins: (process.env.ALLOWED_ORIGINS || '').split(','),
        validateOnline: false,  // Call Laravel to validate token
        laravelValidateUrl: 'http://localhost/api/terminal/validate',
    },
    
    terminal: {
        shell: process.env.TERMINAL_SHELL || '/bin/bash',
        cwd: process.env.TERMINAL_CWD || process.cwd(),
        user: process.env.TERMINAL_USER || null,
        env: {
            TERM: 'xterm-256color',
            LANG: 'en_US.UTF-8',
            ...parseEnv(process.env.TERMINAL_ENV),
        },
    },
    
    limits: {
        maxSessions: parseInt(process.env.MAX_SESSIONS) || 50,
        sessionTimeout: parseInt(process.env.SESSION_TIMEOUT) || 3600,
        idleTimeout: parseInt(process.env.IDLE_TIMEOUT) || 900,
    },
    
    logging: {
        level: process.env.LOG_LEVEL || 'info',
        auditPath: process.env.AUDIT_LOG_PATH,
        logCommands: process.env.LOG_COMMANDS === 'true',
    },
};

function parseEnv(str) {
    if (!str) return {};
    return str.split(',').reduce((acc, pair) => {
        const [key, value] = pair.split('=');
        if (key) acc[key] = value || '';
        return acc;
    }, {});
}
```

---

## UI Customization

### Theme Presets

```php
// config/web-terminal.php

'ui' => [
    // Dark theme (default)
    'theme' => 'dark',
    
    // Or use preset name
    // 'theme' => 'light',
    // 'theme' => 'dracula',
    // 'theme' => 'solarized-dark',
    // 'theme' => 'monokai',
],
```

### Custom Theme

```php
'ui' => [
    'theme' => [
        'background' => '#282a36',      // Dracula background
        'foreground' => '#f8f8f2',
        'cursor' => '#f8f8f2',
        'cursorAccent' => '#282a36',
        'selection' => 'rgba(68, 71, 90, 0.5)',
        
        // ANSI colors
        'black' => '#21222c',
        'red' => '#ff5555',
        'green' => '#50fa7b',
        'yellow' => '#f1fa8c',
        'blue' => '#bd93f9',
        'magenta' => '#ff79c6',
        'cyan' => '#8be9fd',
        'white' => '#f8f8f2',
        
        // Bright variants
        'brightBlack' => '#6272a4',
        'brightRed' => '#ff6e6e',
        'brightGreen' => '#69ff94',
        'brightYellow' => '#ffffa5',
        'brightBlue' => '#d6acff',
        'brightMagenta' => '#ff92df',
        'brightCyan' => '#a4ffff',
        'brightWhite' => '#ffffff',
    ],
],
```

### Filament Dark Mode Integration

The terminal automatically adapts to Filament's dark mode:

```php
// In plugin registration
->theme([
    'background' => 'var(--terminal-bg, #0d0d0d)',
    'foreground' => 'var(--terminal-fg, #d4d4d4)',
])
```

```css
/* resources/css/terminal.css */
:root {
    --terminal-bg: #ffffff;
    --terminal-fg: #1f2937;
}

.dark {
    --terminal-bg: #0d0d0d;
    --terminal-fg: #d4d4d4;
}
```

### Custom Fonts

```php
'ui' => [
    // Use web font
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

## Multi-tenancy

### Per-Tenant Configuration

```php
// In AdminPanelProvider
->plugin(
    ShellGatePlugin::make()
        ->authorize(function () {
            $user = auth()->user();
            $tenant = filament()->getTenant();
            
            // Check tenant-specific permission
            return $user->can('access-terminal', $tenant);
        })
        ->workingDirectory(function () {
            $tenant = filament()->getTenant();
            return "/var/www/tenants/{$tenant->id}";
        })
)
```

### Tenant Isolation

```php
// config/web-terminal.php
'terminal' => [
    'cwd' => function () {
        if ($tenant = filament()->getTenant()) {
            return "/var/www/tenants/{$tenant->id}";
        }
        return base_path();
    },
    
    'env' => function () {
        $tenant = filament()->getTenant();
        return [
            'TERM' => 'xterm-256color',
            'TENANT_ID' => $tenant?->id,
        ];
    },
],
```

### Gateway Multi-tenant Support

```javascript
// Pass tenant info in JWT
const payload = {
    user_id: user.id,
    tenant_id: tenant?.id,
    cwd: tenant ? `/var/www/tenants/${tenant.id}` : '/var/www/app',
};
```

---

## References

- [xterm.js Options](https://xtermjs.org/docs/api/terminal/interfaces/iterminaloptions/)
- [xterm.js Theme](https://xtermjs.org/docs/api/terminal/interfaces/itheme/)
- [Filament Configuration](https://filamentphp.com/docs/5.x/panels/configuration)
