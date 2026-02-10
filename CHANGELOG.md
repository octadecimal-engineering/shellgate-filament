# Changelog

All notable changes to Shell Gate will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] - 2026-02-10

### Fixed

- **Blade Icons** – Register gear icon as `gear.svg` in the shellgate set so Filament navigation resolves (fixes `SvgNotFound: Svg by name "gear" from set "shellgate" not found`)

## [1.1.0] - 2026-02-10

### Added

- macOS-style terminal window frame with title bar, traffic light dots, and gear icon
- Animated connecting overlay with spinner
- Integrated status bar with connection state and session ID
- Resize handle for adjustable terminal height
- Custom ShellGate gear SVG icon for Filament navigation (registered as Blade Icon set)
- Stale session cleanup on page load (auto-closes active sessions on reload)
- Prompt customization and screen clear on WebSocket connect

### Changed

- Complete terminal UI redesign: window frame, title bar, status bar, error banner
- Navigation icon now uses custom `shellgate-gear` Blade Icon instead of heroicon
- Filament page header hidden for immersive terminal experience

## [1.0.0] - 2026-02-09

### Added

- Initial release of Shell Gate
- Real bash terminal in Filament admin panel via PTY + WebSocket
- JWT authentication with IP and User-Agent binding
- Node.js gateway for WebSocket and PTY management
- Session management with database persistence
- Audit logging for security compliance
- Rate limiting and session limits per user
- Configurable terminal appearance (fonts, colors, theme)
- xterm.js frontend with fit addon and resize support
- Docker support for gateway deployment
- Nginx and systemd configuration stubs
- **License verification via Anystack API**
  - Runtime license validation on terminal access
  - Automatic license activation for new domains
  - 24-hour caching for valid licenses
  - Graceful degradation when API is unavailable
  - License verification skipped in local/testing environments
- `LicenseService` and Artisan command `php artisan shell-gate:license` (`--refresh` to re-validate)
- License configuration in `config/shell-gate.php`

### Changed

- **Enterprise-safe installer** – Complete rewrite of `install.sh`
  - Verifies prerequisites (PHP 8.2+, Laravel 11/12, Filament 3+, Node 18+)
  - Does NOT create users or modify permissions
  - Configures gateway JWT and working directory automatically
  - License key prompt (optional, required for production)
  - Clear post-installation instructions with authorization options
- **Simplified INSTALLATION.md** – Quick Start, Authorization Setup (is_super_admin, Spatie, custom), production deployment (Gateway, Nginx, Docker)
- Middleware `EnsureTerminalAccess` checks license validity before user authorization
- Configuration: `license_key` moved to `license.key`

### Fixed

- **shellgate:serve** – Use Symfony Process for TTY detection (fixes `Call to undefined method PendingProcess::isTtySupported()` on older Laravel)
- **Gateway** – `ALLOWED_ORIGINS` empty by default for local dev (avoids CORS issues when unset)
- Gateway `DEFAULT_CWD` set to Laravel project directory (fixes PTY exit on macOS)
- AuditService graceful fallback when `shell-gate-audit` log channel not configured
- Documentation: Standardized `SHELL_GATE_*` env vars, correct config path, `ShellGatePlugin` references, `is_super_admin` boolean cast warning

### Security

- JWT tokens with HS256 signature
- TLS/WSS support via Nginx proxy
- Audit trail for all terminal sessions
- Configurable secrets redaction in logs
- License validation for production use
- Installer does not modify user permissions; access via `->authorize()` or default checks

[Unreleased]: https://github.com/octadecimalhq/shellgate/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/octadecimalhq/shellgate/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/octadecimalhq/shellgate/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/octadecimalhq/shellgate/releases/tag/v1.0.0
