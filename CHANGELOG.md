# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2026-02-16

### Added
- Centralized `Signature_Validator` service for HMAC signature validation
- Enhanced timestamp validation with leading zero rejection and sanity checks (2000-2100)
- CORS wildcard subdomain support with scheme enforcement
- Configurable database version exposure setting (`expose_database_version`)
- HTTP origin warning in settings page for insecure HTTP origins
- Rate limiting for OPTIONS requests to prevent CORS probing attacks
- Comprehensive README.md with 700+ lines of documentation
- Enhanced SECURITY.md with threat model, secret management, and compliance guidance
- Field renderer with description support in admin settings

### Changed
- **BREAKING**: CORS wildcard patterns (`*.example.com`) now match ONLY subdomains, not the base domain itself
  - To match both base and subdomains, add both patterns explicitly:
    - `https://example.com`
    - `https://*.example.com`
- Refactored HMAC validation logic into dedicated `Signature_Validator` service (removed 30+ lines of duplicate code from REST controller)
- Improved code separation and testability with service-oriented architecture
- Updated PHP requirement to 8.0+ (from 7.4+)
- Updated "Tested up to" WordPress version to 6.7

### Security
- Enhanced timestamp validation prevents edge case bypasses with leading zeros
- Scheme enforcement in CORS prevents cross-scheme attacks (http vs https)
- OPTIONS requests now properly rate-limited to prevent abuse
- Timing-safe signature comparison with `hash_equals()`
- Database version exposure is now configurable (can be disabled for minimal information disclosure)

### Fixed
- CORS wildcard matching now correctly enforces scheme matching
- Timestamp validation now rejects malformed timestamps with leading zeros
- Service wiring in plugin container now includes `Signature_Validator`

## [1.0.0] - 2025-08-23

### Added
- Initial release
- REST API endpoint: `GET /wp-json/pcc/v1/snapshot` (permission-gated)
- HMAC signed endpoint: `GET /wp-json/pcc/v1/snapshot/signed` (no WordPress user required)
- WP-CLI command: `wp pcc snapshot`
- Permission-based access control with `pcc_read_snapshot` capability
- Rate limiting with sliding window algorithm
- Caching with WordPress Transients API
- CORS support with origin allow-listing
- Admin settings page with configuration options
- Application Password authentication support
- Optional OAuth2 bearer token support
- Update metadata exposure (optional)
- Multiple HMAC key support with key IDs
- IP address and user ID allow-lists
- Capability-based access control
- Security-focused design (no PII exposure, no telemetry, no remote code execution)

### Security
- Permission-gated endpoints require explicit capability
- HMAC-SHA256 signature authentication with timestamp-based replay protection
- Rate limiting (60 requests per minute by default)
- CORS with explicit origin allow-listing
- No sensitive data in responses
- Transient-based caching for performance

[1.1.0]: https://github.com/yourusername/project-context-connector-wp/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/yourusername/project-context-connector-wp/releases/tag/1.0.0
