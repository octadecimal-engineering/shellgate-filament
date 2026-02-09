# Installation Guide

Shell Gate installation guide for all deployment scenarios.

---

## Table of Contents

**Getting Started**
1. [Quick Start (3 commands)](#quick-start) — Local development
2. [Requirements](#requirements)

**Full Installation**
3. [Step-by-Step Installation](#step-by-step-installation)
4. [Authorization Setup](#authorization-setup)
5. [License Configuration](#license-configuration)

**Production Deployment**
6. [Gateway Setup](#gateway-setup) — Systemd, PM2, Docker
7. [Nginx Configuration](#nginx-configuration)

**Reference**
8. [Artisan Commands](#artisan-commands)
9. [Verification](#verification)
10. [Troubleshooting](#troubleshooting)

---

## Quick Start

For local development — get a working terminal in under 2 minutes.

### Prerequisites

- PHP 8.2+, Laravel 11/12, Filament 3/4/5
- Node.js 18+, npm

### 1. Install Package

```bash
# Add Anystack repository to composer.json
# "repositories": [{"type": "composer", "url": "https://shellgate.composer.sh"}]

composer config http-basic.shellgate.composer.sh YOUR_EMAIL YOUR_LICENSE_KEY
composer require octadecimalhq/shellgate
```

> **Local development (path repository):**
> ```bash
> # "repositories": [{"type": "path", "url": "./packages/octadecimalhq/shellgate"}]
> composer require octadecimalhq/shellgate:@dev
> ```

### 2. Run Installer

```bash
php artisan shellgate:install
```

The installer automatically:
- Publishes `config/shell-gate.php`
- Runs database migrations
- Registers `ShellGatePlugin` in your `AdminPanelProvider.php`
- Adds `SHELL_GATE_GATEWAY_URL` to your `.env`
- Creates gateway `.env` (JWT secret, working directory)
- Installs gateway npm dependencies

> **Tip:** In `local` environment, any authenticated user can access the terminal — no extra setup needed.

### 3. Start Gateway & Test

```bash
php artisan shellgate:serve
```

Visit `/admin/terminal` in your browser. That's it!

---

## Requirements

### Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| PHP | 8.2 | 8.3+ |
| Laravel | 11.0 | 12.x |
| Filament | 3.0 | 5.x |
| Node.js | 18 | 20 LTS |

### PHP Extensions

- `openssl` — JWT signing
- `json` — API responses
- `mbstring` — String handling

### System Requirements (Linux production)

```bash
# For PTY support
apt-get install -y build-essential python3

# For node-pty compilation
npm install -g node-gyp
```

---

## Step-by-Step Installation

Manual installation for full control over each step. The `shellgate:install` command automates all of this — use manual steps only if you need fine-grained control.

### Step 1: Add Composer Repository

Shell Gate is distributed via **[Anystack](https://anystack.sh)**. Add the Anystack Composer repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "composer",
            "url": "https://shellgate.composer.sh"
        }
    ]
}
```

Authenticate with your email and license key:

```bash
composer config http-basic.shellgate.composer.sh YOUR_EMAIL YOUR_LICENSE_KEY
```

> **Local development (path repository):**
>
> Extract the package to `packages/octadecimalhq/shellgate/` and add a path repository instead:
> ```json
> {"type": "path", "url": "./packages/octadecimalhq/shellgate"}
> ```

### Step 2: Install Package

```bash
composer require octadecimalhq/shellgate
```

> **Note:** Use `:@dev` suffix when using path repository with `minimum-stability: stable`.

### Step 3: Publish Configuration

```bash
php artisan vendor:publish --tag=shell-gate-config
```

Creates `config/shell-gate.php`.

### Step 4: Run Migrations

```bash
php artisan migrate
```

Creates `terminal_sessions` table.

### Step 5: Configure Environment

Add to `.env`:

```env
# Gateway URL (local development)
SHELL_GATE_GATEWAY_URL=ws://127.0.0.1:7681

# License (required for production)
SHELL_GATE_LICENSE_KEY=your-license-key
ANYSTACK_CUSTOMER_API_KEY=8AE4QSBwTGwiPyrSvmw6vozlPbbkZr7J
```

### Step 6: Register Plugin

Edit `app/Providers/Filament/AdminPanelProvider.php`:

```php
use OctadecimalHQ\ShellGate\ShellGatePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ... other config
            ->plugin(ShellGatePlugin::make());
    }
}
```

> **Note:** In `local`/`testing` environments, any authenticated user has access by default. For production authorization options, see [Authorization Setup](#authorization-setup).

### Step 7: Setup Gateway

```bash
cd vendor/octadecimalhq/shellgate/gateway
cp .env.example .env
npm install
```

Edit `gateway/.env`:

```env
PORT=7681
JWT_SECRET=your-laravel-app-key  # Must match APP_KEY
DEFAULT_CWD=/path/to/your/laravel/project
```

> **Tip:** `php artisan shellgate:install` and `php artisan shellgate:serve` handle this automatically.

### Step 8: Start Gateway

```bash
php artisan shellgate:serve
```

Or manually:

```bash
cd vendor/octadecimalhq/shellgate/gateway && node index.js
```

---

## Authorization Setup

Shell Gate uses a flexible authorization system with smart defaults.

### Default Behavior (Zero Config)

Without any `->authorize()` configuration:
- **`local`/`testing`:** Any authenticated user can access the terminal
- **`production`:** Requires `is_super_admin` attribute or Spatie `super_admin` role

This means **no authorization setup is needed for local development**.

### Option A: is_super_admin Attribute (Production)

Add a boolean column to users table:

**1. Publish and run migration:**

```bash
php artisan vendor:publish --tag=shell-gate-user-migration
php artisan migrate
```

**2. Add cast to User model:**

```php
// app/Models/User.php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_super_admin' => 'boolean',  // Required!
    ];
}
```

> **Important:** Without the boolean cast, authorization may fail silently.

**3. Grant access:**

```bash
php artisan tinker
>>> User::where('email', 'admin@example.com')->update(['is_super_admin' => true])
```

**4. Register plugin with explicit authorize:**

```php
->plugin(
    ShellGatePlugin::make()
        ->authorize(fn () => auth()->user()?->is_super_admin)
)
```

### Option B: Spatie Permissions

If you use [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission):

**1. Create role:**

```bash
php artisan tinker
>>> Spatie\Permission\Models\Role::create(['name' => 'super_admin'])
>>> User::first()->assignRole('super_admin')
```

**2. Register plugin:**

```php
->plugin(
    ShellGatePlugin::make()
        ->authorize(fn () => auth()->user()?->hasRole('super_admin'))
)
```

### Option C: Custom Authorization

Use any logic you need:

```php
->plugin(
    ShellGatePlugin::make()
        ->authorize(function () {
            $user = auth()->user();

            // Your custom logic
            return $user?->can('access-terminal')
                || $user?->hasRole('developer')
                || $user?->email === 'admin@company.com';
        })
)
```

---

## License Configuration

Shell Gate requires a license for production use.

### Development Mode

License verification is **automatically skipped** when:
- `APP_ENV=local`
- `APP_ENV=testing`

### Production Setup

Add to `.env`:

```env
SHELL_GATE_LICENSE_KEY=your-license-key-here
ANYSTACK_CUSTOMER_API_KEY=8AE4QSBwTGwiPyrSvmw6vozlPbbkZr7J
```

| Key | Source |
|-----|--------|
| `SHELL_GATE_LICENSE_KEY` | Your purchase confirmation email |
| `ANYSTACK_CUSTOMER_API_KEY` | Copy exactly as shown above (same for all customers) |

### Check License Status

```bash
php artisan shell-gate:license
php artisan shell-gate:license --refresh  # Force re-validation
```

### License Statuses

| Status | Action |
|--------|--------|
| `valid` | None required |
| `expired` | Renew your license via Anystack |
| `activation_limit` | Upgrade license or deactivate another domain |
| `missing_key` | Add `SHELL_GATE_LICENSE_KEY` to .env |

---

## Gateway Setup

The terminal gateway is a Node.js process managing PTY sessions.

### Local Development

```bash
php artisan shellgate:serve
```

This auto-detects the gateway path, installs dependencies if needed, and starts the server.

Alternatively, start manually:

```bash
cd vendor/octadecimalhq/shellgate/gateway
npm install
npm start
```

### Production: Systemd

Create `/etc/systemd/system/shell-gate-gateway.service`:

```ini
[Unit]
Description=Shell Gate Terminal Gateway
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/app/vendor/octadecimalhq/shellgate/gateway
ExecStart=/usr/bin/node index.js
Restart=on-failure
RestartSec=10

Environment=NODE_ENV=production
Environment=PORT=7681
Environment=JWT_SECRET=your-laravel-app-key
Environment=ALLOWED_ORIGINS=https://yourdomain.com
Environment=DEFAULT_CWD=/var/www/app

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable shell-gate-gateway
sudo systemctl start shell-gate-gateway
```

### Production: PM2

```bash
npm install -g pm2
cd vendor/octadecimalhq/shellgate/gateway
pm2 start index.js --name shell-gate-gateway
pm2 save
pm2 startup
```

### Production: Docker

```bash
docker run -d \
  --name shell-gate-gateway \
  --restart unless-stopped \
  -p 127.0.0.1:7681:7681 \
  -e JWT_SECRET="your-app-key" \
  -e ALLOWED_ORIGINS="https://yourdomain.com" \
  -e DEFAULT_CWD="/app" \
  -v $(pwd):/app:ro \
  octadecimalhq/shellgate-gateway
```

Or use Docker Compose — see `gateway/docker-compose.yml`.

---

## Nginx Configuration

Proxy WebSocket connections through Nginx for TLS termination.

```nginx
server {
    listen 443 ssl http2;
    server_name yourdomain.com;

    # SSL certificates
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Your Laravel application
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Terminal WebSocket proxy
    location /ws/terminal {
        proxy_pass http://127.0.0.1:7681;
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
    }
}
```

Update `.env` to use wss:// in production:

```env
SHELL_GATE_GATEWAY_URL=wss://yourdomain.com/ws/terminal
```

Test and reload:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `shellgate:install` | One-command installer (config, migrations, plugin registration, gateway) |
| `shellgate:serve` | Start the terminal gateway (development) |
| `shell-gate:license` | Check license status |
| `shell-gate:close-sessions` | Close active terminal sessions |

### shellgate:install

```bash
php artisan shellgate:install [options]
```

| Option | Description |
|--------|-------------|
| `--dev` | Development mode (skip license prompts) |
| `--no-migrate` | Skip database migrations |
| `--no-gateway` | Skip gateway setup (npm install) |
| `--force` | Overwrite existing configuration |

### shellgate:serve

```bash
php artisan shellgate:serve [options]
```

| Option | Description |
|--------|-------------|
| `--port=7681` | Port to listen on |
| `--host=127.0.0.1` | Host to bind to |
| `--install` | Force npm install before starting |

---

## Verification

### Check Gateway Status

```bash
# Systemd
sudo systemctl status shell-gate-gateway

# PM2
pm2 status

# Port listening
ss -tlnp | grep 7681

# Health endpoint
curl http://127.0.0.1:7681/health
```

### Test from Browser

1. Log in to Filament admin panel
2. Navigate to **System > Terminal** (or whatever label you configured)
3. Type `whoami` and press Enter
4. Verify the response

### Check Logs

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Gateway logs (PM2)
pm2 logs shell-gate-gateway
```

---

## Troubleshooting

### "Connection closed (code 4006)" on macOS

**Cause:** `node-pty` spawn-helper binary missing execute permission.

**Fix:**
```bash
cd vendor/octadecimalhq/shellgate/gateway
npm install  # Postinstall script fixes permissions
```

Or manually:
```bash
chmod +x node_modules/node-pty/prebuilds/darwin-*/spawn-helper
```

### Terminal not visible in sidebar

**Cause:** Authorization failing silently.

**Fix (development):** Ensure you are logged in. In `local` environment, any authenticated user has access.

**Fix (production):** Add `is_super_admin` cast to User model:
```php
protected function casts(): array
{
    return [
        'is_super_admin' => 'boolean',
    ];
}
```

### "Invalid token" or "Token expired"

**Cause:** JWT_SECRET mismatch between Laravel and gateway.

**Fix:** Re-run the installer or check manually:
```bash
php artisan shellgate:install --force --no-migrate
```

Or ensure gateway `.env` has same key as Laravel `APP_KEY`:
```env
# Gateway .env
JWT_SECRET=base64:xxxxx  # Same as APP_KEY in Laravel .env
```

### "Maximum session limit reached"

**Fix:** Close stale sessions:
```bash
php artisan shell-gate:close-sessions
php artisan shell-gate:close-sessions --user=1
```

Or increase limit in `config/shell-gate.php`:
```php
'limits' => [
    'max_sessions_per_user' => 10,
],
```

### WebSocket connection failed

1. Check gateway is running: `curl http://127.0.0.1:7681/health`
2. Check Nginx config: `sudo nginx -t`
3. Verify URL in `.env`: `SHELL_GATE_GATEWAY_URL=wss://...`

### Getting Help

1. Check [GitHub Issues](https://github.com/octadecimalhq/shellgate/issues)
2. Email: support@octadecimal.engineering (license holders)

---

## Next Steps

- [Configuration Guide](CONFIGURATION.md) — Customize terminal behavior
- [Security Guide](SECURITY.md) — Harden your installation
- [API Reference](API.md) — Integrate with other systems
