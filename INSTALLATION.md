# Installation Guide

Complete installation guide for Shell Gate, covering all deployment scenarios.

---

## Table of Contents

1. [Quick install via script](#quick-install-via-script) — **fastest** (see ⚠ [warning](#quick-install-via-script))
2. [Requirements](#requirements)
3. [Quick Install](#quick-install)
4. [Step-by-Step Installation](#step-by-step-installation)
5. [Gateway Setup](#gateway-setup)
6. [Nginx Configuration](#nginx-configuration)
7. [Docker Deployment](#docker-deployment)
8. [Verification](#verification)
9. [Troubleshooting](#troubleshooting)

---

## Quick install via script

> ### ⚠ WARNING — Use the install script at your own risk
>
> **The vendor recommends installing Shell Gate by following this documentation** ([Step-by-Step Installation](#step-by-step-installation)) **rather than using the install script.** The script is provided for convenience only.
>
> **The vendor does not accept any responsibility for decisions you make or consequences that result from running the script.** By using it you acknowledge that:
>
> - The script will **overwrite** `config/shell-gate.php` (with `--force`).
> - The script will run **database migrations** (`php artisan migrate --force`), which will change your database schema.
> - The script will **read** your Laravel `.env` (e.g. `APP_KEY`) and **create or update** `vendor/octadecimal/shell-gate/gateway/.env`.
> - The script will run **`npm install`** in the gateway directory (third-party dependencies).
>
> **You must run the script only if you understand and accept these actions.** Prefer the [Step-by-Step Installation](#step-by-step-installation) if you want full control over each change. On production, consider running migrations and config publishes manually instead of using the script.

---

**Fastest path:** if the package is already installed (e.g. `composer require octadecimal/shell-gate:@dev`), run the install script from your **Laravel project root**:

```bash
# From Laravel project root (where artisan and composer.json live)
bash vendor/octadecimal/shell-gate/install.sh
```

If you use a path repository (package in `packages/octadecimal/shell-gate`):

```bash
bash packages/octadecimal/shell-gate/install.sh
```

The script will:

- Publish `config/shell-gate.php`
- Run migrations (terminal_sessions table)
- Create `gateway/.env` from `.env.example` and copy `APP_KEY` from Laravel as `JWT_SECRET`
- Run `npm install` in the gateway (including the macOS `spawn-helper` fix)

**You still need to:**

1. Publish and run the optional user migration if you use `is_super_admin`:  
   `php artisan vendor:publish --tag=shell-gate-user-migration && php artisan migrate`
2. Register the plugin in `AdminPanelProvider.php` (see [Step 5](#step-5-register-plugin)).
3. Set `SHELL_GATE_GATEWAY_URL` in Laravel `.env` (e.g. `ws://127.0.0.1:7681` for local).
4. Start the gateway: `cd vendor/octadecimal/shell-gate/gateway && npm start`.

See [Step-by-Step Installation](#step-by-step-installation) for details and [Troubleshooting](#troubleshooting) if something fails.

---

## Requirements

### Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.2 | 8.3+ |
| Laravel | 11.28 | 12.x |
| Filament | 3.0 or 5.0 | 5.x latest |
| Livewire | 4.0 | 4.x latest |
| Node.js | 18 | 20 LTS |
| npm | 9 | 10+ |

### PHP Extensions

```bash
# Required extensions
php -m | grep -E "openssl|json|mbstring|tokenizer"
```

- `openssl` — JWT signing
- `json` — API responses
- `mbstring` — String handling
- `tokenizer` — Laravel requirement

### System Requirements

```bash
# For PTY support (Linux)
apt-get install -y build-essential python3

# For node-pty compilation
npm install -g node-gyp
```

---

## Quick Install

For experienced users who want to get started quickly.

**Note:** The package is **not on Packagist**. You must add a Composer repository first (path repository for local dev, or the private repo URL provided after purchase). Then:

```bash
# 1. Install package (after adding repo to composer.json)
# If using path repo and your app has "minimum-stability": "stable", use:
composer require octadecimal/shell-gate:@dev
# Otherwise (private versioned repo):
composer require octadecimal/shell-gate

# 2. Publish config and optional user migration, then migrate
php artisan vendor:publish --tag=shell-gate-config
php artisan vendor:publish --tag=shell-gate-user-migration   # adds is_super_admin to users (if you use default auth)
php artisan migrate

# 3. Start gateway (development)
cd vendor/octadecimal/shell-gate/gateway
npm install && npm start

# 4. Register plugin in AdminPanelProvider.php
# ->plugin(ShellGatePlugin::make()->authorize(fn () => auth()->user()?->is_super_admin))

# 5. Visit /admin/terminal
```

If you use the default `authorize(fn () => auth()->user()?->is_super_admin)` callback, your `User` model must have an `is_super_admin` attribute. Step 2 publishes an optional migration that adds this column; after migrating, set `is_super_admin = true` for users who may access the terminal (e.g. via a seeder or tinker).

**Important:** You must also cast `is_super_admin` as `boolean` in your `User` model:

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',  // Required for authorization
    ];
}
```

---

## Step-by-Step Installation

### Step 1: Add Composer Repository and Install Package

Shell Gate is distributed outside Packagist (commercial / private). Add a repository in your application’s `composer.json`:

**Path repository (local development, e.g. when testing inside a monorepo):**

1. Place the package so that your application can reference it at `./packages/octadecimal/shell-gate`. For example:
   - **From a .zip archive:** Unzip the archive and copy the **contents** of the extracted folder (the one that contains `composer.json`, `src/`, `config/`, etc.) into `packages/octadecimal/shell-gate` inside your project root (create the directory if needed).
   - **From git:** Clone or add as a submodule into `packages/octadecimal/shell-gate`.
2. Add the path repository to your application’s `composer.json`:

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

**Private repository (after purchase):** use the repository URL and any auth details provided (e.g. Anystack, Satis, or private Packagist).

Then install:

```bash
composer require octadecimal/shell-gate
```

- **If your application has `"minimum-stability": "stable"`** and you use the path repository, Composer may reject the package (path repos are treated as `dev-main`). Use:
  ```bash
  composer require octadecimal/shell-gate:@dev
  ```
- For a specific version (when using a versioned private repo):
  ```bash
  composer require octadecimal/shell-gate:^1.0
  ```

### Step 2: Publish Configuration

```bash
# Publish config file
php artisan vendor:publish --tag=shell-gate-config

# Publish views (optional, for customization)
php artisan vendor:publish --tag=shell-gate-views

# Publish assets (optional, if you build your own)
php artisan vendor:publish --tag=shell-gate-assets
```

This creates:
- `config/shell-gate.php` — Main configuration
- `resources/views/vendor/shell-gate/` — Blade templates (if published)
- `public/vendor/shell-gate/` — Assets (if published)

### Step 3: Run Migrations

```bash
php artisan migrate
```

This creates the `terminal_sessions` table (loaded from the package).

**Optional — `is_super_admin` on users:** If you use the default authorization callback `auth()->user()?->is_super_admin`, your `User` model must have an `is_super_admin` attribute. To add it via migration:

```bash
php artisan vendor:publish --tag=shell-gate-user-migration
php artisan migrate
```

Then set `is_super_admin = true` for users who may access the terminal (e.g. in a seeder or `php artisan tinker`). If you use a different authorization callback (e.g. a role or permission), you can skip this.

**Important:** You must also cast `is_super_admin` as `boolean` in your `User` model for the authorization to work correctly:

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',  // Required for authorization
    ];
}
```

Without this cast, the attribute may return `1` (integer) instead of `true` (boolean), causing authorization to fail.

### Step 4: Configure Environment

Add to your `.env` file:

```env
# Terminal Gateway URL (adjust for your setup)
SHELL_GATE_GATEWAY_URL=wss://yourdomain.com/ws/terminal

# JWT secret (uses APP_KEY by default, can override)
# SHELL_GATE_JWT_SECRET=your-secret-key

# Gateway settings
SHELL_GATE_GATEWAY_HOST=127.0.0.1
SHELL_GATE_GATEWAY_PORT=7681
```

### Step 5: Register Plugin

Edit `app/Providers/Filament/AdminPanelProvider.php`:

```php
<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Octadecimal\ShellGate\ShellGatePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            // ... other configuration
            ->plugin(
                ShellGatePlugin::make()
                    ->authorize(fn () => auth()->user()?->is_super_admin)
                    ->navigationGroup('System')
                    ->navigationLabel('Terminal')
            );
    }
}
```

#### Adding the Terminal to the sidebar (left menu)

Registering the plugin as above **automatically** adds a "Terminal" item to the Filament admin sidebar. The link appears under the **navigation group** and with the **label** you pass:

- **`->navigationGroup('System')`** — Puts the item in the "System" group (collapse section in the sidebar). Use any group name you use elsewhere (e.g. `'Tools'`, `'Settings'`).
- **`->navigationLabel('Terminal')`** — Text shown in the menu. Default is `'Terminal'` if omitted.

You can also set **icon** and **sort order**:

```php
->plugin(
    ShellGatePlugin::make()
        ->authorize(fn () => auth()->user()?->is_super_admin)
        ->navigationGroup('System')
        ->navigationLabel('Terminal')
        ->navigationIcon('heroicon-o-command-line')  // optional, default terminal icon
        ->navigationSort(100)                         // optional, lower = higher in list
);
```

**Via config / .env:** If you prefer not to hardcode in the provider, use `config/shell-gate.php` (after `php artisan vendor:publish --tag=shell-gate-config`) or environment variables:

```env
SHELL_GATE_NAV_GROUP=System
SHELL_GATE_NAV_LABEL=Terminal
SHELL_GATE_NAV_SORT=100
```

Then in the provider you can omit the fluent calls and the page will use config defaults.

**Hide from sidebar:** To keep the terminal reachable only by direct URL (e.g. `/admin/terminal`) and not show it in the sidebar:

```php
->plugin(
    ShellGatePlugin::make()
        ->authorize(...)
        ->hideFromNavigation()
);
```

You can still add a custom link elsewhere (e.g. a Filament widget on the dashboard) that points to the terminal page route (e.g. `route('filament.admin.pages.terminal')` or your panel's equivalent).

### Step 6: Clear Caches

```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
php artisan filament:clear-cached-components
```

---

## Gateway Setup

The Terminal Gateway is a Node.js process that manages PTY sessions.

### Option A: Local Development

```bash
# Navigate to gateway directory
cd vendor/octadecimal/shell-gate/gateway

# Install dependencies (postinstall fixes PTY permissions on macOS — see Troubleshooting)
npm install

# Start gateway
npm start

# Or with custom port
PORT=7681 JWT_SECRET=your-app-key npm start
```

**macOS users:** After `npm install`, a postinstall script runs automatically and sets execute permission on `node-pty`'s `spawn-helper` binary. This prevents "Connection closed (code 4006)" / "posix_spawnp failed". If you use `npm ci --ignore-scripts` or copied the gateway without running `npm install`, run `npm install` once in the gateway directory so the fix is applied.

### Option B: Systemd Service (Production)

Create `/etc/systemd/system/shell-gate-gateway.service`:

```ini
[Unit]
Description=Web Terminal Gateway
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/app/vendor/octadecimal/shell-gate/gateway
ExecStart=/usr/bin/node index.js
Restart=on-failure
RestartSec=10

Environment=NODE_ENV=production
Environment=PORT=7681
Environment=JWT_SECRET=your-laravel-app-key
Environment=ALLOWED_ORIGINS=https://yourdomain.com
Environment=TERMINAL_CWD=/var/www/app
Environment=TERMINAL_USER=terminal-user

[Install]
WantedBy=multi-user.target
```

Enable and start:

```bash
sudo systemctl daemon-reload
sudo systemctl enable shell-gate-gateway
sudo systemctl start shell-gate-gateway
sudo systemctl status shell-gate-gateway
```

### Option C: PM2 Process Manager

```bash
# Install PM2 globally
npm install -g pm2

# Start gateway with PM2
cd vendor/octadecimal/shell-gate/gateway
pm2 start index.js --name shell-gate-gateway

# Save PM2 configuration
pm2 save

# Setup PM2 startup script
pm2 startup
```

### Option D: Docker

See [Docker Deployment](#docker-deployment) section below.

---

## Nginx Configuration

### Basic WebSocket Proxy

Add to your Nginx server block:

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    # SSL certificates
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # ... your existing Laravel configuration ...

    # WebSocket proxy for terminal
    location /ws/terminal {
        proxy_pass http://127.0.0.1:7681;
        proxy_http_version 1.1;
        
        # WebSocket upgrade headers
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        
        # Pass client info
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Long timeouts for persistent connections
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
        
        # Disable buffering for real-time
        proxy_buffering off;
    }
}
```

### Full Example Configuration

```nginx
# /etc/nginx/sites-available/yourdomain.com

upstream terminal_gateway {
    server 127.0.0.1:7681;
    keepalive 32;
}

server {
    listen 80;
    server_name yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    root /var/www/app/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

    # Security Headers
    add_header Strict-Transport-Security "max-age=63072000" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Laravel Application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Terminal WebSocket
    location /ws/terminal {
        proxy_pass http://terminal_gateway;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 86400;
        proxy_send_timeout 86400;
        proxy_buffering off;
        proxy_cache off;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Test and reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

---

## Docker Deployment

### Using Docker Compose

Create `docker-compose.terminal.yml`:

```yaml
version: '3.8'

services:
  terminal-gateway:
    build:
      context: ./vendor/octadecimal/shell-gate/gateway
      dockerfile: Dockerfile
    container_name: shell-gate-gateway
    restart: unless-stopped
    ports:
      - "127.0.0.1:7681:7681"
    environment:
      - NODE_ENV=production
      - PORT=7681
      - JWT_SECRET=${APP_KEY}
      - ALLOWED_ORIGINS=${APP_URL}
      - TERMINAL_CWD=/app
    volumes:
      - ./:/app:ro  # Mount app directory read-only
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:7681/health"]
      interval: 30s
      timeout: 10s
      retries: 3

networks:
  app-network:
    external: true
```

Start with:

```bash
docker-compose -f docker-compose.terminal.yml up -d
```

### Standalone Docker

```bash
# Build image
docker build -t octadecimal/shell-gate-gateway \
  vendor/octadecimal/shell-gate/gateway

# Run container
docker run -d \
  --name shell-gate-gateway \
  --restart unless-stopped \
  -p 127.0.0.1:7681:7681 \
  -e JWT_SECRET="your-app-key" \
  -e ALLOWED_ORIGINS="https://yourdomain.com" \
  -e TERMINAL_CWD="/app" \
  -v $(pwd):/app:ro \
  octadecimal/shell-gate-gateway
```

### Gateway Dockerfile

```dockerfile
# vendor/octadecimal/shell-gate/gateway/Dockerfile
FROM node:20-alpine

WORKDIR /gateway

# Install build dependencies for node-pty
RUN apk add --no-cache python3 make g++ bash

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy source
COPY . .

# Create non-root user
RUN adduser -D gateway
USER gateway

EXPOSE 7681

CMD ["node", "index.js"]
```

---

## Verification

### Step 1: Check Gateway Status

```bash
# If using systemd
sudo systemctl status shell-gate-gateway

# If using PM2
pm2 status

# If using Docker
docker ps | grep terminal

# Check if port is listening
netstat -tlnp | grep 7681
# or
ss -tlnp | grep 7681
```

### Step 2: Test WebSocket Connection

```bash
# Install wscat
npm install -g wscat

# Test connection (replace with your token)
wscat -c "wss://yourdomain.com/ws/terminal?token=TEST"

# Should see connection error for invalid token (expected)
# error: Unexpected server response: 401
```

### Step 3: Test from Browser

1. Log in to Filament admin panel
2. Navigate to **System > Terminal**
3. You should see the terminal interface
4. Type `whoami` and press Enter
5. Verify the response

### Step 4: Check Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Terminal audit logs
tail -f storage/logs/terminal-audit.log

# Gateway logs (if using PM2)
pm2 logs shell-gate-gateway

# Gateway logs (if using Docker)
docker logs -f shell-gate-gateway
```

---

## Troubleshooting

### Issue: "Connection closed (code 4006)" or "posix_spawnp failed" (macOS)

**Symptoms:**
- Terminal page connects briefly then shows "Connection closed (code 4006)"
- Gateway logs show: `PTY spawn failed` / `posix_spawnp failed`

**Cause:** On macOS, the `node-pty` dependency ships a native helper binary (`spawn-helper`) in `prebuilds/darwin-*/`. Some npm installs do not set the executable bit on this file, so the OS blocks execution and the PTY cannot be created.

**Solution:**

1. **Let postinstall fix it (recommended):** From the gateway directory run:
   ```bash
   cd vendor/octadecimal/shell-gate/gateway
   npm install
   ```
   The package's `postinstall` script sets `chmod +x` on all `spawn-helper` binaries. Then restart the gateway (`npm start`).

2. **Manual fix:** If you cannot run `npm install` (e.g. read-only deploy), set the bit by hand:
   ```bash
   chmod +x vendor/octadecimal/shell-gate/gateway/node_modules/node-pty/prebuilds/darwin-arm64/spawn-helper
   # On Intel Mac use darwin-x64 instead of darwin-arm64
   ```

3. **Docker:** Not affected; the image builds with correct permissions.

### Issue: "Cannot redeclare static ... \$view as non static" (Filament 3)

**Symptoms:** Fatal error when loading the panel or running `artisan migrate`, mentioning `TerminalPage` and `$view`.

**Cause:** Filament 3 declares `$view` as `static` on the base Page class; the plugin must match.

**Solution:** Ensure you use a Shell Gate version that supports Filament 3 (e.g. `composer.json` in the package allows `"filament/filament": "^3.0|^5.0"` and `TerminalPage` uses `protected static string $view`). If you maintain a fork, change `protected string $view` to `protected static string $view` in `TerminalPage.php`.

### Issue: "WebSocket connection failed"

**Symptoms:**
- Terminal page loads but shows "Connection failed"
- Browser console shows WebSocket error

**Solutions:**

1. **Check gateway is running:**
   ```bash
   curl http://127.0.0.1:7681/health
   ```

2. **Check Nginx WebSocket config:**
   ```bash
   sudo nginx -t
   grep -A 10 "ws/terminal" /etc/nginx/sites-available/yourdomain.com
   ```

3. **Verify wss:// URL in .env:**
   ```env
   SHELL_GATE_GATEWAY_URL=wss://yourdomain.com/ws/terminal
   ```

4. **Check firewall:**
   ```bash
   sudo ufw status
   # Port 7681 should NOT be open publicly (Nginx proxies it)
   ```

### Issue: "Invalid token" or "Token expired"

**Symptoms:**
- WebSocket connects but immediately closes with 4001/4002

**Solutions:**

1. **Verify JWT_SECRET matches APP_KEY:**
   ```bash
   # In .env
   APP_KEY=base64:xxxxx
   
   # Gateway should use same key
   JWT_SECRET=base64:xxxxx
   ```

2. **Check server time synchronization:**
   ```bash
   timedatectl status
   # Ensure NTP is active
   ```

3. **Increase token TTL temporarily for debugging:**
   ```php
   // config/shell-gate.php
   'token_ttl' => 600, // 10 minutes
   ```

### Issue: Terminal menu not visible in sidebar (authorization fails silently)

**Symptoms:**
- Plugin is registered but Terminal does not appear in Filament sidebar
- User has `is_super_admin = 1` in database but cannot access terminal
- No error messages shown

**Cause:** The `is_super_admin` attribute is not cast as `boolean` in the User model. Without the cast, Laravel returns the raw database value (`1` or `0` as integer/string), and the `authorize` callback may not evaluate correctly.

**Solution:** Add the `is_super_admin` cast to your `User` model:

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',  // Add this line
    ];
}
```

After adding the cast, clear caches:

```bash
php artisan config:clear
php artisan view:clear
```

### Issue: "Permission denied" when executing commands

**Symptoms:**
- Terminal connects but commands fail with permission errors

**Solutions:**

1. **Check terminal user permissions:**
   ```bash
   # See which user the gateway runs as
   ps aux | grep node
   
   # Verify user can access app directory
   sudo -u www-data ls -la /var/www/app
   ```

2. **Verify TERMINAL_CWD setting:**
   ```bash
   # In gateway environment
   TERMINAL_CWD=/var/www/app
   ```

### Issue: Gateway crashes or restarts

**Symptoms:**
- Terminal disconnects randomly
- Gateway logs show errors

**Solutions:**

1. **Check Node.js memory:**
   ```bash
   # Increase memory limit
   NODE_OPTIONS="--max-old-space-size=512" node index.js
   ```

2. **Check for unhandled exceptions:**
   ```bash
   # View full logs
   pm2 logs shell-gate-gateway --lines 100
   ```

3. **Update node-pty:**
   ```bash
   cd vendor/octadecimal/shell-gate/gateway
   npm update node-pty
   npm rebuild
   ```

### Issue: Terminal is slow or laggy

**Symptoms:**
- Noticeable delay between keypress and response
- Choppy output

**Solutions:**

1. **Check network latency:**
   ```bash
   ping yourdomain.com
   ```

2. **Disable WebGL addon if issues:**
   ```javascript
   // In terminal configuration
   'use_webgl' => false,
   ```

3. **Check server load:**
   ```bash
   top
   htop
   ```

### Getting Help

If you've tried the above and still have issues:

1. **Check GitHub Issues:** Look for similar problems
2. **Collect diagnostics:**
   ```bash
   php artisan about
   node --version
   npm --version
   cat /etc/os-release
   ```
3. **Open support ticket:** support@octadecimal.engineering (license holders)

---

## Next Steps

- [Configuration Guide](CONFIGURATION.md) — Customize terminal behavior
- [Security Guide](SECURITY.md) — Harden your installation
- [API Reference](API.md) — Integrate with other systems
