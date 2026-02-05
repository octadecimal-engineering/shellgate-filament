#!/usr/bin/env bash
#
# Shell Gate — quick install script
# Run from your Laravel project root:
#   bash vendor/octadecimal/shell-gate/install.sh
# Or (path repo): bash packages/octadecimal/shell-gate/install.sh
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

echo "1/5 Publishing config..."
php artisan vendor:publish --tag=shell-gate-config --force

echo ""
echo "2/5 User migration (adds is_super_admin to users)? Run:"
echo "   php artisan vendor:publish --tag=shell-gate-user-migration"
echo "   php artisan migrate"
echo "   Then set is_super_admin = true for users who may access the terminal."
echo ""

echo "3/5 Running migrations..."
php artisan migrate --force

echo ""
echo "4/5 Gateway: preparing .env and dependencies..."
if [[ ! -f "$GATEWAY_DIR/.env" ]]; then
  cp "$GATEWAY_DIR/.env.example" "$GATEWAY_DIR/.env"
  if [[ -f "$LARAVEL_ROOT/.env" ]]; then
    APP_KEY=$(grep -E '^APP_KEY=' "$LARAVEL_ROOT/.env" | cut -d= -f2- | tr -d '"' | tr -d "'")
    if [[ -n "$APP_KEY" ]]; then
      if grep -q '^JWT_SECRET=' "$GATEWAY_DIR/.env"; then
        if [[ "$(uname -s)" = Darwin ]]; then
          sed -i '' "s|^JWT_SECRET=.*|JWT_SECRET=$APP_KEY|" "$GATEWAY_DIR/.env"
        else
          sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$APP_KEY|" "$GATEWAY_DIR/.env"
        fi
      else
        echo "JWT_SECRET=$APP_KEY" >> "$GATEWAY_DIR/.env"
      fi
      echo "   Set JWT_SECRET in gateway/.env from Laravel APP_KEY."
    fi
  fi
  echo "   Created gateway/.env — review and adjust if needed."
else
  echo "   gateway/.env already exists, skipping."
fi

cd "$GATEWAY_DIR"
npm install
cd "$LARAVEL_ROOT"

echo ""
echo "5/5 Done."
echo "---"
echo "Next steps:"
echo "  1. Register the plugin in app/Providers/Filament/AdminPanelProvider.php:"
echo "     ->plugin(ShellGatePlugin::make()"
echo "         ->authorize(fn () => auth()->user()?->is_super_admin)"
echo "         ->navigationGroup('System')"
echo "         ->navigationLabel('Terminal'))"
echo ""
echo "  2. In Laravel .env set (e.g. for local dev):"
echo "     SHELL_GATE_GATEWAY_URL=ws://127.0.0.1:7681"
echo ""
echo "  3. Start the gateway:"
echo "     cd $GATEWAY_DIR && npm start"
echo ""
echo "  4. Visit your admin panel → System → Terminal"
echo ""
