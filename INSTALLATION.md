# Installation Guide

Shell Gate installation guide for all deployment scenarios.

---

## Table of Contents

**Getting Started**
1. [Quick Start (5 minutes)](#quick-start) — Local development
2. [Requirements](#requirements)

**Full Installation**
3. [Step-by-Step Installation](#step-by-step-installation)
4. [Authorization Setup](#authorization-setup)
5. [License Configuration](#license-configuration)

**Production Deployment**
6. [Gateway Setup](#gateway-setup) — Systemd, PM2, Docker
7. [Nginx Configuration](#nginx-configuration)

**Reference**
8. [Verification](#verification)
9. [Troubleshooting](#troubleshooting)

---

## Quick Start

For local development — get a working terminal in 5 minutes.

### Prerequisites

- PHP 8.2+, Laravel 11/12, Filament 3/4/5
- Node.js 18+, npm

### 1. Install Package

```bash
# Add path repository to composer.json (if using ZIP distribution)
# "repositories": [{"type": "path", "url": "./packages/octadecimal/shell-gate"}]

composer require octadecimal/shell-gate:@dev
```

### 2. Run Installer

```bash
bash vendor/octadecimal/shell-gate/install.sh
```

The installer will:
- Publish configuration
- Run migrations
- Configure gateway (JWT secret, working directory)
- Prompt for license key (optional for local dev)

### 3. Register Plugin

Edit `app/Providers/Filament/AdminPanelProvider.php`:

```php
use Octadecimal\ShellGate\ShellGatePlugin;

->plugin(
    ShellGatePlugin::make()
        ->authorize(fn () => auth()->user()?->is_super_admin)
)
```

### 4. Setup User Access

The default authorization checks `is_super_admin` attribute. Add it to your User model:

```bash
php artisan vendor:publish --tag=shell-gate-user-migration
php artisan migrate
```

Add cast to `app/Models/User.php`:

```php
protected function casts(): array
{
    return [
        // ... existing casts
        'is_super_admin' => 'boolean',
    ];
}
```

Grant access to a user:

```bash
php artisan tinker
>>> User::first()->update(['is_super_admin' => true])
```

### 5. Start Gateway & Test

```bash
cd vendor/octadecimal/shell-gate/gateway && npm install && npm start
```

Visit `/admin/terminal` in your browser.

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

Detailed installation for full control over each step.

### Step 1: Add Composer Repository

Shell Gate is distributed outside Packagist. Add a repository to `composer.json`:

**From ZIP archive:**

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

Extract the ZIP contents into `packages/octadecimal/shell-gate/`.

**Private repository (after purchase):**

Use the repository URL provided in your purchase confirmation.

### Step 2: Install Package

```bash
composer require octadecimal/shell-gate:@dev
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
use Octadecimal\ShellGate\ShellGatePlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            // ... other config
            ->plugin(
                ShellGatePlugin::make()
                    ->authorize(fn () => auth()->user()?->is_super_admin)
                    ->navigationGroup('System')
                    ->navigationLabel('Terminal')
            );
    }
}
```

### Step 7: Setup Gateway

```bash
cd vendor/octadecimal/shell-gate/gateway
cp .env.example .env
npm install
```

Edit `gateway/.env`:

```env
PORT=7681
JWT_SECRET=your-laravel-app-key  # Must match APP_KEY
DEFAULT_CWD=/path/to/your/laravel/project
```

> **Tip:** The install script (`install.sh`) configures this automatically.

### Step 8: Clear Caches

```bash
php artisan config:clear
php artisan view:clear
php artisan route:clear
```

---

## Authorization Setup

Shell Gate uses a flexible authorization system. Choose one approach:

### Option A: is_super_admin Attribute (Default)

The simplest approach — add a boolean column to users table.

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

**4. Register plugin:**

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

### Default Behavior (No Callback)

If you don't specify `->authorize()`, Shell Gate checks:
1. `is_super_admin` attribute (if exists)
2. Spatie `super_admin` role (if Spatie is installed)

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
| `expired` | Renew at octadecimal.engineering |
| `activation_limit` | Upgrade license or deactivate another domain |
| `missing_key` | Add `SHELL_GATE_LICENSE_KEY` to .env |

---

## Gateway Setup

The terminal gateway is a Node.js process managing PTY sessions.

### Local Development

```bash
cd vendor/octadecimal/shell-gate/gateway
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
WorkingDirectory=/var/www/app/vendor/octadecimal/shell-gate/gateway
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
cd vendor/octadecimal/shell-gate/gateway
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
  octadecimal/shell-gate-gateway
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

## Verification

### Check Gateway Status

```bash
# Systemd
sudo systemctl status shell-gate-gateway

# PM2
pm2 status

# Port listening
ss -tlnp | grep 7681
```

### Test from Browser

1. Log in to Filament admin panel
2. Navigate to **System > Terminal**
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
cd vendor/octadecimal/shell-gate/gateway
npm install  # Postinstall script fixes permissions
```

Or manually:
```bash
chmod +x node_modules/node-pty/prebuilds/darwin-*/spawn-helper
```

### Terminal not visible in sidebar

**Cause:** Authorization failing silently (missing boolean cast).

**Fix:** Add cast to User model:
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

**Fix:** Ensure gateway `.env` has same key as Laravel `APP_KEY`:
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

1. Check [GitHub Issues](https://github.com/octadecimal-engineering/shellgate-filament/issues)
2. Email: support@octadecimal.engineering (license holders)

---

## Next Steps

- [Configuration Guide](CONFIGURATION.md) — Customize terminal behavior
- [Security Guide](SECURITY.md) — Harden your installation
- [API Reference](API.md) — Integrate with other systems
