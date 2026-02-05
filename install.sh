#!/usr/bin/env bash
#
# Shell Gate — installation script
# Run from your Laravel project root:
#   bash vendor/octadecimal/shell-gate/install.sh
# Or (path repo): bash packages/octadecimal/shell-gate/install.sh
#
# The script will prompt you to ensure
# JWT secrets match between Laravel and the gateway before continuing.
#
# Risks and mitigations:
#   - sed with APP_KEY: value is escaped for \ and & to avoid injection.
#   - migrate --force: on APP_ENV=production the script warns and asks for Enter.
#   - read: only blocks when stdin is a TTY (CI/pipe-friendly).
#   - Only reads .env and writes gateway/.env; does not modify Laravel .env.
#
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PACKAGE_ROOT="$SCRIPT_DIR"

# Find Laravel root (directory containing artisan)
find_laravel_root() {
  local dir="$PACKAGE_ROOT"
  while [[ -n "$dir" && "$dir" != "/" ]]; do
    if [[ -f "$dir/artisan" ]]; then
      echo "$dir"
      return
    fi
    dir="$(dirname "$dir")"
  done
  return 1
}

LARAVEL_ROOT=""
if [[ -f "$PACKAGE_ROOT/../../../artisan" ]]; then
  LARAVEL_ROOT="$(cd "$PACKAGE_ROOT/../../.." && pwd)"
elif [[ -f "$PACKAGE_ROOT/../../artisan" ]]; then
  LARAVEL_ROOT="$(cd "$PACKAGE_ROOT/../.." && pwd)"
else
  LARAVEL_ROOT="$(find_laravel_root)" || true
fi

if [[ -z "$LARAVEL_ROOT" || ! -f "$LARAVEL_ROOT/artisan" ]]; then
  echo "Error: Could not find Laravel project root (directory with artisan)."
  echo "Run this script from your Laravel project, e.g.:"
  echo "  bash vendor/octadecimal/shell-gate/install.sh"
  exit 1
fi

cd "$LARAVEL_ROOT"
echo "Laravel root: $LARAVEL_ROOT"
echo ""

# Checks
command -v php >/dev/null 2>&1 || { echo "Error: PHP not found."; exit 1; }
command -v composer >/dev/null 2>&1 || { echo "Error: Composer not found."; exit 1; }
command -v node >/dev/null 2>&1 || { echo "Error: Node.js not found (required for gateway)."; exit 1; }
command -v npm >/dev/null 2>&1 || { echo "Error: npm not found."; exit 1; }

# Package must be installed
if [[ -d "$LARAVEL_ROOT/vendor/octadecimal/shell-gate" ]]; then
  GATEWAY_DIR="$LARAVEL_ROOT/vendor/octadecimal/shell-gate/gateway"
elif [[ -d "$PACKAGE_ROOT/gateway" ]]; then
  GATEWAY_DIR="$PACKAGE_ROOT/gateway"
else
  echo "Error: Shell Gate package not found. Install it first:"
  echo "  composer require octadecimal/shell-gate:@dev"
  echo "  (or add path repo and run composer require)"
  exit 1
fi

# --- Red warning: run at your own risk ---
RED='\033[1;31m'
YELLOW='\033[1;33m'
NC='\033[0m'
echo ""
echo -e "${RED}  ⚠  WARNING: YOU ARE RUNNING THIS SCRIPT AT YOUR OWN RISK.${NC}"
echo -e "${RED}  The vendor recommends installation via the documentation (Step-by-Step Installation).${NC}"
echo -e "${RED}  The vendor does not accept any responsibility for decisions you make or consequences${NC}"
echo -e "${RED}  that result from running this script. You must proceed consciously.${NC}"
echo ""
echo "This script will:"
echo "  • Overwrite config/shell-gate.php (publish --force)"
echo "  • Run database migrations (php artisan migrate --force)"
echo "  • Create or update gateway/.env (using APP_KEY from Laravel .env)"
echo "  • Run npm install in the gateway directory"
echo ""
echo -e "${YELLOW}Type YES (in capital letters) to accept and continue, or anything else to exit:${NC}"
if [[ -t 0 ]]; then
  read -r CONFIRM
  if [[ "$CONFIRM" != "YES" ]]; then
    echo "Aborted. No changes were made."
    exit 1
  fi
else
  if [[ "${SHELL_GATE_INSTALL_ACCEPT:-}" != "YES" ]]; then
    echo "Non-interactive mode and SHELL_GATE_INSTALL_ACCEPT is not YES. Aborted."
    exit 1
  fi
  echo "(SHELL_GATE_INSTALL_ACCEPT=YES detected, continuing.)"
fi
echo ""

# Helper: ask for y/N confirmation
confirm() {
  local msg="$1"
  if [[ ! -t 0 ]]; then
    echo "$msg (Non-interactive: continuing.)"
    return 0
  fi
  echo -e "${YELLOW}$msg${NC}"
  read -r reply
  [[ "$reply" =~ ^[yY]$ ]]
}

echo "=== Step 1/6: Publishing config ==="
echo "This will overwrite the file: config/shell-gate.php (if it already exists)."
if ! confirm "Do you want to continue? [y/N]"; then
  echo "Aborted."
  exit 1
fi
php artisan vendor:publish --tag=shell-gate-config --force
echo ""

# --- JWT secret: which files to edit and wait for user ---
echo "=== Step 2/6: JWT secret (required for terminal connection) ==="
echo ""
echo "To avoid 'Invalid token' errors, the JWT secret must be IDENTICAL in Laravel and the gateway."
echo ""
echo "Files to edit:"
echo "  1) Laravel application:"
echo "     File: $LARAVEL_ROOT/.env"
echo "     Add or set: SHELL_GATE_JWT_SECRET=<same value as APP_KEY>"
echo "     (If you omit SHELL_GATE_JWT_SECRET, Laravel uses APP_KEY by default.)"
echo ""
echo "  2) Gateway:"
echo "     File: $GATEWAY_DIR/.env"
echo "     Set: JWT_SECRET=<exact same value as in Laravel (APP_KEY or SHELL_GATE_JWT_SECRET)>"
echo ""

# Create or update gateway .env
if [[ ! -f "$GATEWAY_DIR/.env" ]]; then
  cp "$GATEWAY_DIR/.env.example" "$GATEWAY_DIR/.env"
  echo "Created gateway/.env from .env.example."
  if [[ -f "$LARAVEL_ROOT/.env" ]]; then
    APP_KEY=$(grep -E '^APP_KEY=' "$LARAVEL_ROOT/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")
    if [[ -n "$APP_KEY" ]]; then
      # Escape for sed replacement: \ and & are special
      APP_KEY_SED="${APP_KEY//\\/\\\\}"
      APP_KEY_SED="${APP_KEY_SED//&/\\&}"
      if [[ "$(uname -s)" = Darwin ]]; then
        sed -i '' "s|^JWT_SECRET=.*|JWT_SECRET=$APP_KEY_SED|" "$GATEWAY_DIR/.env"
      else
        sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$APP_KEY_SED|" "$GATEWAY_DIR/.env"
      fi
      echo "Pre-filled JWT_SECRET in gateway/.env from Laravel APP_KEY."
    fi
  fi
  # Empty ALLOWED_ORIGINS for local dev so browser can connect
  if grep -q '^ALLOWED_ORIGINS=' "$GATEWAY_DIR/.env"; then
    if [[ "$(uname -s)" = Darwin ]]; then
      sed -i '' "s|^ALLOWED_ORIGINS=.*|ALLOWED_ORIGINS=|" "$GATEWAY_DIR/.env"
    else
      sed -i "s|^ALLOWED_ORIGINS=.*|ALLOWED_ORIGINS=|" "$GATEWAY_DIR/.env"
    fi
  fi
else
  echo "Gateway .env already exists. Ensure JWT_SECRET matches Laravel (see above)."
fi

echo ""
echo "Press Enter when you have verified that the same JWT secret is set in both files (or to accept the current values and continue)..."
if [[ -t 0 ]]; then
  read -r
else
  echo "(Non-interactive mode: continuing without prompt.)"
fi

# --- Verify tokens match ---
get_laravel_secret() {
  if [[ -f "$LARAVEL_ROOT/.env" ]]; then
    local sg=$(grep -E '^SHELL_GATE_JWT_SECRET=' "$LARAVEL_ROOT/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")
    if [[ -n "$sg" ]]; then
      echo "$sg"
      return
    fi
    local appkey=$(grep -E '^APP_KEY=' "$LARAVEL_ROOT/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")
    echo "$appkey"
  fi
}

get_gateway_secret() {
  if [[ -f "$GATEWAY_DIR/.env" ]]; then
    grep -E '^JWT_SECRET=' "$GATEWAY_DIR/.env" | cut -d= -f2- | tr -d '"' | tr -d "'"
  fi
}

LARAVEL_SECRET=$(get_laravel_secret)
GATEWAY_SECRET=$(get_gateway_secret)

if [[ -z "$LARAVEL_SECRET" ]]; then
  echo "Error: Could not read JWT secret from Laravel (.env: APP_KEY or SHELL_GATE_JWT_SECRET)."
  exit 1
fi

if [[ -z "$GATEWAY_SECRET" ]]; then
  echo "Error: Could not read JWT_SECRET from gateway .env ($GATEWAY_DIR/.env)."
  exit 1
fi

if [[ "$LARAVEL_SECRET" != "$GATEWAY_SECRET" ]]; then
  echo "Error: JWT secrets do not match."
  echo "  Laravel uses: ${LARAVEL_SECRET:0:20}..."
  echo "  Gateway uses: ${GATEWAY_SECRET:0:20}..."
  echo "Edit the files above so both use the same value, then run this script again."
  exit 1
fi

echo "JWT secret verification: OK (both sides match)."
echo ""

echo "=== Step 3/6: User migration (optional) ==="
echo "To allow access by is_super_admin users, run:"
echo "  php artisan vendor:publish --tag=shell-gate-user-migration"
echo "  php artisan migrate"
echo "Then set is_super_admin = true for users who may access the terminal."
echo ""

echo "=== Step 4/6: Running migrations ==="
echo "This will run: php artisan migrate --force"
echo "Your database schema will be modified (e.g. terminal_sessions table, and any pending migrations)."
if [[ -f "$LARAVEL_ROOT/.env" ]] && grep -qE '^APP_ENV=production' "$LARAVEL_ROOT/.env"; then
  echo -e "${RED}WARNING: APP_ENV=production detected. You are about to run migrations on a production database.${NC}"
fi
if ! confirm "Do you want to run migrations now? [y/N]"; then
  echo "Skipping migrations. You can run them later with: php artisan migrate --force"
else
  if [[ -f "$LARAVEL_ROOT/.env" ]] && grep -qE '^APP_ENV=production' "$LARAVEL_ROOT/.env"; then
    echo -e "${YELLOW}Press Enter one more time to confirm production migrations, or Ctrl+C to abort.${NC}"
    [[ -t 0 ]] && read -r
  fi
  php artisan migrate --force
fi
echo ""

echo "=== Step 5/6: Gateway dependencies ==="
echo "This will run: npm install in $GATEWAY_DIR"
echo "Third-party packages will be downloaded and installed (e.g. node-pty, ws)."
if ! confirm "Do you want to run npm install now? [y/N]"; then
  echo "Skipping npm install. You can run it later with: cd $GATEWAY_DIR && npm install"
else
  cd "$GATEWAY_DIR"
  npm install
  cd "$LARAVEL_ROOT"
fi
echo ""

echo "=== Step 6/6: Final setup (automatic where possible) ==="
ADMIN_PROVIDER="$LARAVEL_ROOT/app/Providers/Filament/AdminPanelProvider.php"
ENV_FILE="$LARAVEL_ROOT/.env"
GATEWAY_URL_LINE="SHELL_GATE_GATEWAY_URL=ws://127.0.0.1:7681"

# 6a. Add SHELL_GATE_GATEWAY_URL to Laravel .env if missing
if [[ -f "$ENV_FILE" ]]; then
  if grep -qE '^SHELL_GATE_GATEWAY_URL=' "$ENV_FILE"; then
    echo "  SHELL_GATE_GATEWAY_URL already set in .env"
  else
    echo "" >> "$ENV_FILE"
    echo "# Shell Gate (added by install script)" >> "$ENV_FILE"
    echo "$GATEWAY_URL_LINE" >> "$ENV_FILE"
    echo "  Added SHELL_GATE_GATEWAY_URL to .env"
  fi
else
  echo "  Skipping .env (file not found). Add manually: $GATEWAY_URL_LINE"
fi

# 6b. Check if plugin is registered; if not, show instruction
if [[ -f "$ADMIN_PROVIDER" ]]; then
  if grep -q "ShellGatePlugin" "$ADMIN_PROVIDER"; then
    echo "  Plugin already registered in AdminPanelProvider.php"
  else
    echo "  Plugin not registered. Add manually in app/Providers/Filament/AdminPanelProvider.php (see below)."
  fi
else
  echo "  AdminPanelProvider.php not found. Register the plugin manually after creating the Filament panel."
fi

echo "---"
echo "Done. Remaining steps (if any):"
echo "  1. If the plugin is not registered yet, edit app/Providers/Filament/AdminPanelProvider.php:"
echo "     use Octadecimal\\ShellGate\\ShellGatePlugin;"
echo "     ->plugin(ShellGatePlugin::make()"
echo "         ->authorize(fn () => auth()->user()?->is_super_admin ?? false)"
echo "         ->navigationGroup('System')"
echo "         ->navigationLabel('ShellGate'))"
echo ""
echo "  2. Restart the gateway if it was already running:"
echo "     cd $GATEWAY_DIR && npm start"
echo ""
echo "  3. Visit your admin panel → System → ShellGate"
echo ""
