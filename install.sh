#!/usr/bin/env bash
#
# Shell Gate — Enterprise Installer
# ==================================
#
# This installer verifies prerequisites, installs the package via Composer,
# and prints post-installation instructions.
#
# SECURITY NOTICE:
#   This installer does NOT create users, modify permissions, or make
#   security decisions. Access control is configured via the plugin's
#   ->authorize() callback or default is_super_admin / Spatie role checks.
#
# Usage:
#   1. Extract the ZIP archive in your Laravel project root
#   2. Run: bash install.sh
#
set -e

# ------------------------------------------------------------------------------
# Configuration
# ------------------------------------------------------------------------------
PACKAGE_NAME="octadecimalhq/shellgate"
PACKAGE_PATH="./packages/octadecimalhq/shellgate"
MIN_PHP_VERSION="8.2"
SUPPORTED_LARAVEL="11|12"
MIN_NODE_VERSION="18"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color
BOLD='\033[1m'

# ------------------------------------------------------------------------------
# Helper Functions
# ------------------------------------------------------------------------------
print_header() {
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}  Shell Gate Installer${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

print_step() {
    echo -e "${BLUE}[${1}]${NC} ${2}"
}

print_success() {
    echo -e "    ${GREEN}✓${NC} ${1}"
}

print_error() {
    echo -e "    ${RED}✗${NC} ${1}"
}

print_warning() {
    echo -e "    ${YELLOW}!${NC} ${1}"
}

fail() {
    echo ""
    echo -e "${RED}Installation failed:${NC} ${1}"
    echo ""
    exit 1
}

version_gte() {
    # Returns 0 if $1 >= $2
    [ "$(printf '%s\n' "$2" "$1" | sort -V | head -n1)" = "$2" ]
}

# ------------------------------------------------------------------------------
# Prerequisite Checks
# ------------------------------------------------------------------------------
check_prerequisites() {
    print_step "1/6" "Checking prerequisites..."
    local errors=0

    # Check if we're in a Laravel project
    if [[ ! -f "artisan" ]]; then
        print_error "Not a Laravel project (artisan not found)"
        print_warning "Run this script from your Laravel project root"
        errors=$((errors + 1))
    else
        print_success "Laravel project detected"
    fi

    # Check PHP version
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
        if version_gte "$PHP_VERSION" "$MIN_PHP_VERSION"; then
            print_success "PHP $PHP_VERSION"
        else
            print_error "PHP $PHP_VERSION (requires >= $MIN_PHP_VERSION)"
            errors=$((errors + 1))
        fi
    else
        print_error "PHP not found"
        errors=$((errors + 1))
    fi

    # Check Laravel version
    if [[ -f "artisan" ]]; then
        LARAVEL_VERSION=$(php artisan --version 2>/dev/null | grep -oE '[0-9]+\.[0-9]+' | head -1 | cut -d. -f1)
        if [[ "$LARAVEL_VERSION" =~ ^($SUPPORTED_LARAVEL)$ ]]; then
            print_success "Laravel $LARAVEL_VERSION"
        else
            print_error "Laravel $LARAVEL_VERSION (requires 11 or 12)"
            errors=$((errors + 1))
        fi
    fi

    # Check Filament
    if [[ -f "composer.lock" ]] && grep -q '"filament/filament"' composer.lock; then
        FILAMENT_VERSION=$(grep -A1 '"filament/filament"' composer.lock | grep '"version"' | grep -oE '[0-9]+\.[0-9]+' | head -1)
        if [[ "${FILAMENT_VERSION%%.*}" -ge 3 ]]; then
            print_success "Filament $FILAMENT_VERSION"
        else
            print_error "Filament $FILAMENT_VERSION (requires v3+)"
            errors=$((errors + 1))
        fi
    else
        print_error "Filament not installed"
        print_warning "Install Filament first: composer require filament/filament"
        errors=$((errors + 1))
    fi

    # Check Composer
    if command -v composer &> /dev/null; then
        print_success "Composer available"
    else
        print_error "Composer not found"
        errors=$((errors + 1))
    fi

    # Check Node.js
    if command -v node &> /dev/null; then
        NODE_VERSION=$(node -v | grep -oE '[0-9]+' | head -1)
        if [[ "$NODE_VERSION" -ge "$MIN_NODE_VERSION" ]]; then
            print_success "Node.js v$NODE_VERSION"
        else
            print_error "Node.js v$NODE_VERSION (requires >= $MIN_NODE_VERSION)"
            errors=$((errors + 1))
        fi
    else
        print_error "Node.js not found (required for terminal gateway)"
        errors=$((errors + 1))
    fi

    # Check npm
    if command -v npm &> /dev/null; then
        print_success "npm available"
    else
        print_error "npm not found"
        errors=$((errors + 1))
    fi

    # Check if package files exist
    if [[ -d "$PACKAGE_PATH" ]]; then
        print_success "Package files found at $PACKAGE_PATH"
    else
        print_error "Package files not found at $PACKAGE_PATH"
        print_warning "Ensure the ZIP was extracted correctly"
        errors=$((errors + 1))
    fi

    # Check writable directories
    if [[ -w "composer.json" ]]; then
        print_success "composer.json is writable"
    else
        print_error "composer.json is not writable"
        errors=$((errors + 1))
    fi

    if [[ $errors -gt 0 ]]; then
        fail "$errors prerequisite(s) not met. Please fix the issues above and try again."
    fi

    echo ""
}

# ------------------------------------------------------------------------------
# Composer Setup
# ------------------------------------------------------------------------------
setup_composer() {
    print_step "2/6" "Configuring Composer..."

    # Add path repository if not already present
    if grep -q "packages/octadecimalhq/shellgate" composer.json 2>/dev/null; then
        print_success "Repository already configured"
    else
        composer config repositories.shell-gate path "$PACKAGE_PATH" --no-interaction 2>/dev/null
        print_success "Added path repository"
    fi

    # Require package if not already installed
    if grep -q '"octadecimalhq/shellgate"' composer.json 2>/dev/null; then
        print_success "Package already in composer.json"
    else
        print_warning "Installing package (this may take a moment)..."
        composer require "$PACKAGE_NAME:@dev" --no-interaction 2>&1 | while read -r line; do
            if [[ "$line" == *"Installing"* ]] || [[ "$line" == *"Generating"* ]]; then
                echo "    $line"
            fi
        done
        print_success "Package installed"
    fi

    echo ""
}

# ------------------------------------------------------------------------------
# Publish Assets
# ------------------------------------------------------------------------------
publish_assets() {
    print_step "3/6" "Publishing configuration..."

    php artisan vendor:publish --tag=shell-gate-config --force 2>/dev/null
    print_success "Published config/shell-gate.php"

    echo ""
}

# ------------------------------------------------------------------------------
# Database Migrations
# ------------------------------------------------------------------------------
run_migrations() {
    print_step "4/6" "Running migrations..."

    # Run package migrations
    php artisan migrate --force 2>/dev/null
    print_success "Migrations complete"

    echo ""
}

# ------------------------------------------------------------------------------
# Gateway Setup
# ------------------------------------------------------------------------------
setup_gateway() {
    print_step "5/6" "Setting up terminal gateway..."

    GATEWAY_DIR="vendor/octadecimalhq/shellgate/gateway"

    if [[ ! -d "$GATEWAY_DIR" ]]; then
        GATEWAY_DIR="$PACKAGE_PATH/gateway"
    fi

    if [[ ! -d "$GATEWAY_DIR" ]]; then
        print_error "Gateway directory not found"
        return 1
    fi

    # Create gateway .env from example
    if [[ ! -f "$GATEWAY_DIR/.env" ]]; then
        if [[ -f "$GATEWAY_DIR/.env.example" ]]; then
            cp "$GATEWAY_DIR/.env.example" "$GATEWAY_DIR/.env"
            print_success "Created gateway/.env"
        fi
    else
        print_success "Gateway .env already exists"
    fi

    # Configure JWT secret from Laravel APP_KEY
    if [[ -f ".env" ]] && [[ -f "$GATEWAY_DIR/.env" ]]; then
        APP_KEY=$(grep -E '^APP_KEY=' .env | cut -d= -f2- | tr -d '"' | tr -d "'")
        if [[ -n "$APP_KEY" ]]; then
            # Escape special characters for sed
            APP_KEY_ESCAPED=$(printf '%s\n' "$APP_KEY" | sed -e 's/[\/&]/\\&/g')
            if [[ "$(uname -s)" = "Darwin" ]]; then
                sed -i '' "s|^JWT_SECRET=.*|JWT_SECRET=$APP_KEY_ESCAPED|" "$GATEWAY_DIR/.env"
            else
                sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$APP_KEY_ESCAPED|" "$GATEWAY_DIR/.env"
            fi
            print_success "Configured JWT secret"
        fi
    fi

    # Set DEFAULT_CWD to Laravel project directory
    LARAVEL_ROOT=$(pwd)
    if [[ -f "$GATEWAY_DIR/.env" ]]; then
        if [[ "$(uname -s)" = "Darwin" ]]; then
            sed -i '' "s|^DEFAULT_CWD=.*|DEFAULT_CWD=$LARAVEL_ROOT|" "$GATEWAY_DIR/.env"
        else
            sed -i "s|^DEFAULT_CWD=.*|DEFAULT_CWD=$LARAVEL_ROOT|" "$GATEWAY_DIR/.env"
        fi
        print_success "Configured working directory"
    fi

    # Add gateway URL to Laravel .env if not present
    if [[ -f ".env" ]]; then
        if ! grep -q "SHELL_GATE_GATEWAY_URL" .env; then
            echo "" >> .env
            echo "# Shell Gate" >> .env
            echo "SHELL_GATE_GATEWAY_URL=ws://127.0.0.1:7681" >> .env
            print_success "Added SHELL_GATE_GATEWAY_URL to .env"
        else
            print_success "Gateway URL already configured"
        fi
    fi

    # Install npm dependencies
    print_warning "Installing gateway dependencies..."
    cd "$GATEWAY_DIR"
    npm install --silent 2>&1 | grep -v "^npm" || true
    cd - > /dev/null
    print_success "Gateway dependencies installed"

    echo ""
}

# ------------------------------------------------------------------------------
# License Key
# ------------------------------------------------------------------------------
setup_license() {
    print_step "6/6" "License configuration..."

    if [[ -f ".env" ]] && grep -q "SHELL_GATE_LICENSE_KEY" .env; then
        print_success "License key already configured"
    else
        echo ""
        echo -e "    ${YELLOW}Shell Gate requires a license key for production use.${NC}"
        echo "    Purchase at: https://anystack.sh"
        echo ""
        echo -n "    Enter license key (or press Enter to skip): "

        if [[ -t 0 ]]; then
            read -r LICENSE_KEY
        else
            LICENSE_KEY="${SHELL_GATE_LICENSE_KEY:-}"
        fi

        if [[ -n "$LICENSE_KEY" ]]; then
            echo "" >> .env
            echo "SHELL_GATE_LICENSE_KEY=$LICENSE_KEY" >> .env
            print_success "License key saved"
        else
            print_warning "Skipped (required for production)"
        fi
    fi

    echo ""
}

# ------------------------------------------------------------------------------
# Post-Install Instructions
# ------------------------------------------------------------------------------
print_instructions() {
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BOLD}  Installation Complete${NC}"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${YELLOW}${BOLD}  NEXT STEPS${NC}"
    echo ""
    echo "  1. Register the plugin in your AdminPanelProvider.php:"
    echo ""
    echo -e "     ${BLUE}use OctadecimalHQ\\ShellGate\\ShellGatePlugin;${NC}"
    echo ""
    echo "     ->plugin("
    echo "         ShellGatePlugin::make()"
    echo "             ->authorize(fn () => auth()->user()?->is_super_admin)"
    echo "     )"
    echo ""
    echo "  2. Add is_super_admin to your User model (if not using Spatie roles):"
    echo ""
    echo -e "     ${BLUE}php artisan vendor:publish --tag=shell-gate-user-migration${NC}"
    echo -e "     ${BLUE}php artisan migrate${NC}"
    echo ""
    echo "     Then add to app/Models/User.php casts():"
    echo -e "         ${BLUE}'is_super_admin' => 'boolean',${NC}"
    echo ""
    echo "  3. Grant access to users:"
    echo ""
    echo "     Set is_super_admin = true for authorized users (via tinker/seeder)"
    echo "     Or use Spatie: assign 'super_admin' role"
    echo ""
    echo "  4. Start the terminal gateway:"
    echo ""
        echo -e "     ${BLUE}cd vendor/octadecimalhq/shellgate/gateway && npm start${NC}"
    echo ""
    echo "  5. Visit /admin/terminal"
    echo ""
    echo -e "${RED}${BOLD}  SECURITY NOTICE${NC}"
    echo "  No users have terminal access by default."
    echo "  You must explicitly grant access via is_super_admin or Spatie roles."
    echo "  See INSTALLATION.md for custom authorization options."
    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
}

# ------------------------------------------------------------------------------
# Main
# ------------------------------------------------------------------------------
main() {
    print_header
    check_prerequisites
    setup_composer
    publish_assets
    run_migrations
    setup_gateway
    setup_license
    print_instructions
}

main "$@"
