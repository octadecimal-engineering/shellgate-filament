# Changelog

All notable changes to Shell Gate will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.0] - 2026-02-05

### Added

- **License verification via Anystack API**
  - Runtime license validation on terminal access
  - Automatic license activation for new domains
  - 24-hour caching for valid licenses (reduces API calls)
  - Graceful degradation when API is unavailable
  - License verification skipped in local/testing environments
- New `LicenseService` for Anystack API integration
- New Artisan command: `php artisan shell-gate:license`
  - Check license status
  - `--refresh` flag to force re-validation
- License configuration in `config/shell-gate.php`
- Updated `install.sh` with license key prompt

### Changed

- Middleware `EnsureTerminalAccess` now checks license validity before user authorization
- Configuration structure: `license_key` moved to `license.key`

### Security

- License validation prevents unauthorized production use
- API credentials embedded securely (not exposed to end users)

## [1.0.0] - 2026-02-01

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
- Comprehensive security features:
  - Token expiration (configurable TTL)
  - IP address binding
  - User-Agent binding
  - Rate limiting
  - Origin validation (CORS)
  - Idle and session timeouts
- Filament 5 plugin integration:
  - Custom authorization callback
  - Navigation configuration
  - Gateway URL configuration
- Full test suite (Unit and Feature tests)

### Security

- JWT tokens with HS256 signature
- TLS/WSS support via Nginx proxy
- Audit trail for all terminal sessions
- Configurable secrets redaction in logs

[Unreleased]: https://github.com/octadecimalhq/shell-gate/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/octadecimalhq/shell-gate/releases/tag/v1.0.0
