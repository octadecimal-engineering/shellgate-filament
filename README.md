# Shell Gate

**A real bash terminal in your Filament admin panel — powered by PTY + WebSocket.**

[![Filament v5](https://img.shields.io/badge/Filament-v5-blue)](https://filamentphp.com)
[![Laravel 11+](https://img.shields.io/badge/Laravel-11%2B-red)](https://laravel.com)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-purple)](https://php.net)
[![License: Commercial](https://img.shields.io/badge/License-Commercial-green)](LICENSE-COMMERCIAL.md)

**Repository:** [github.com/octadecimal-engineering/shellgate-filament](https://github.com/octadecimal-engineering/shellgate-filament)

Shell Gate is a **standalone** product. It may be developed or tested inside another workspace (e.g. via Composer path repository) but has no dependency on that workspace.

---

## Overview

**Shell Gate** brings a fully functional bash terminal directly into your Filament v5 admin panel. Unlike command-by-command runners, this plugin provides a **persistent PTY session** over **WebSocket (wss://)**, giving you the exact same experience as SSH — but in your browser.

```
┌─────────────────────────────────────────────────────────────────┐
│  admin@server:~$  ls -la                                        │
│  total 48                                                       │
│  drwxr-xr-x  12 www-data www-data  4096 Feb  1 10:30 .          │
│  drwxr-xr-x   3 root     root      4096 Jan 15 08:00 ..         │
│  -rw-r--r--   1 www-data www-data  1234 Feb  1 10:30 .env       │
│  drwxr-xr-x   8 www-data www-data  4096 Feb  1 10:25 app        │
│  -rwxr-xr-x   1 www-data www-data  1686 Jan 20 12:00 artisan    │
│  admin@server:~$  █                                             │
└─────────────────────────────────────────────────────────────────┘
```

---

## Features

### Core Functionality
- **Real PTY Session** — Not just command execution; a true pseudo-terminal with full readline, history, tab completion
- **Persistent Session** — Your session stays alive while the page is open; run long commands, use vim, htop, etc.
- **WebSocket (wss://)** — Real-time bidirectional communication with TLS encryption
- **xterm.js Frontend** — Industry-standard terminal emulator (powers VS Code, JupyterLab, Portainer)

### Security
- **JWT Authentication** — Short-lived tokens (5-10 min) for each session
- **Role-Based Access** — Restrict to super_admin or custom roles
- **User Isolation** — Optional chroot, containerized shell, or dedicated system user
- **Audit Logging** — Full session logging: who, when, which commands
- **TLS Encryption** — All traffic encrypted via wss://

### Filament Integration
- **Native Page** — Seamless integration into Filament navigation
- **Dark Mode Support** — Respects Filament's theme
- **Multi-Panel** — Works with multiple Filament panels
- **Configurable** — Extensive configuration via `config/shell-gate.php`

### DevOps Ready
- **Docker Support** — Includes Dockerfile for the terminal gateway
- **Nginx Config** — Ready-to-use WebSocket proxy configuration
- **Horizontal Scaling** — Gateway can run as separate service

---

## Requirements

| Component | Version |
|-----------|---------|
| PHP | 8.2+ |
| Laravel | 11.28+ |
| Filament | 5.0+ |
| Livewire | 4.0+ |
| Node.js | 18+ (for Terminal Gateway) |

---

## Quick Start

### 1. Install the Package

Shell Gate is **not on Packagist**. Use one of these options:

**Option A — Path repository (local development / testing):**

In your application’s `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/octadecimal/shell-gate"
        }
    ]
}
```

Then:

```bash
composer require octadecimal/shell-gate
```

**Option B — After purchase (private repo or Anystack):**

Add the Composer repository you received (URL + auth if required), then:

```bash
composer require octadecimal/shell-gate
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=shell-gate-config
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Start Terminal Gateway

```bash
# Using npm (development)
cd vendor/octadecimal/shell-gate/gateway
npm install
npm start

# Using Docker (production)
docker run -d \
  --name shell-gate-gateway \
  -p 7681:7681 \
  -e JWT_SECRET=your-laravel-app-key \
  octadecimal/shell-gate-gateway
```

### 5. Register Plugin

```php
// app/Providers/Filament/AdminPanelProvider.php

use Octadecimal\ShellGate\ShellGatePlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->plugin(
            ShellGatePlugin::make()
                ->authorize(fn () => auth()->user()?->is_super_admin)
        );
}
```

### 6. Configure Nginx (Production)

```nginx
# WebSocket proxy for terminal
location /ws/terminal {
    proxy_pass http://127.0.0.1:7681;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "upgrade";
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_read_timeout 86400;
}
```

---

## Screenshots

### Terminal in Filament Panel
```
┌──────────────────────────────────────────────────────────────────────┐
│  🔧 System Tools  >  Web Terminal                               [×] │
├──────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  admin@myapp:~$ php artisan about                                   │
│                                                                      │
│    Environment .......................... production                 │
│    Debug Mode ........................... DISABLED                   │
│    URL .................................. https://myapp.com          │
│    Maintenance Mode ..................... OFF                        │
│                                                                      │
│  admin@myapp:~$ docker compose ps                                   │
│  NAME         SERVICE    STATUS     PORTS                           │
│  app          app        running    0.0.0.0:8000->8000/tcp          │
│  mysql        mysql      running    3306/tcp                        │
│  redis        redis      running    6379/tcp                        │
│                                                                      │
│  admin@myapp:~$ █                                                   │
│                                                                      │
└──────────────────────────────────────────────────────────────────────┘
```

---

## Documentation

- [Architecture](ARCHITECTURE.md) — System design, components, data flow
- [Security](SECURITY.md) — Threat model, isolation, hardening
- [Installation](INSTALLATION.md) — Detailed setup guide
- [Configuration](CONFIGURATION.md) — All configuration options
- [API Reference](API.md) — REST endpoints, WebSocket protocol

---

## Support

### Commercial License

This plugin is sold under a commercial license. Each license includes:

- **Single Site License** — Use on one production domain
- **Unlimited License** — Use on unlimited domains
- **1 Year Updates** — Free updates for 12 months
- **Priority Support** — Email support with 48h response time

See [LICENSE-COMMERCIAL.md](LICENSE-COMMERCIAL.md) for full terms.

### Getting Help

- **Documentation** — Start with the docs above
- **GitHub Issues** — Bug reports and feature requests
- **Email Support** — support@octadecimal.engineering (license holders)
- **Discord** — Community support in #plugins channel

---

## Credits

Built with these amazing open-source projects:

- [Filament](https://filamentphp.com) — Admin panel framework
- [xterm.js](https://xtermjs.org) — Terminal emulator
- [node-pty](https://github.com/microsoft/node-pty) — PTY for Node.js
- [Laravel](https://laravel.com) — PHP framework

---

## License

Copyright © 2026 Octadecimal Engineering. All rights reserved.

This is commercial software. See [LICENSE-COMMERCIAL.md](LICENSE-COMMERCIAL.md) for licensing terms.

Unauthorized copying, modification, or distribution of this software is strictly prohibited.
