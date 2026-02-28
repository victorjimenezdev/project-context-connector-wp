=== Site Contextsnap ===
Contributors: victorjimenezdev
Tags: rest api, cli, devops, telemetry-free, slack
Requires at least: 6.1
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Expose a sanitized, read-only project snapshot via REST and WP-CLI for Slack/Teams prompts, scripts, and CI.

== Description ==

Site Contextsnap emits a **high-signal, stable JSON** snapshot of your WordPress project for use in:
- Chat workflows (Slack/Teams prompt building)
- CI pipelines and local scripts
- Support and incident response automation

**Snapshot contents**
- WordPress core version, PHP version, DB server/version, environment type, locale
- Active plugins and MU plugins with versions and activation scope; update flags from core transients
- Active theme (and parent), versions, and key theme data
- Selected non‑PII flags: `WP_DEBUG`, `SCRIPT_DEBUG`, `DISALLOW_FILE_EDIT`, `MULTISITE`, `WP_ENVIRONMENT_TYPE`, `WP_CACHE`
- Optional inclusion of update metadata (core/theme/plugin)

**Endpoints**
- `GET /wp-json/pcc/v1/snapshot` (permission‑gated)
- `GET /wp-json/pcc/v1/snapshot/signed` (HMAC‑signed, no WP user)

**Authentication (for /snapshot)**
- *Recommended:* WordPress Application Passwords (Basic Auth over HTTPS)
- If a popular JWT/OAuth2 plugin is installed, Bearer tokens are accepted when that plugin authenticates the request (feature‑gated in settings)

**Access control, caching, and rate limiting**
- Capability `pcc_read_snapshot` (maps to `manage_options` by default; filterable)
- Optional allow‑lists for capabilities, user IDs, and IPs
- Per‑origin/IP rate limiting with sliding‑window approximation; returns `429` with `Retry-After`
- Snapshot caching with configurable TTL; invalidated on plugin/theme activation/deactivation, upgrades, or theme switch
- CORS allow‑list for browser clients; never widened without admin consent

**Privacy**
- No telemetry by default; no secrets or PII in responses; no remote code execution; no code obfuscation/minification
- If you opt in to expose update metadata, only public update info is added

**WP‑CLI**
- `wp pcc snapshot` outputs the same JSON schema used by the REST endpoints

== Installation ==

1. Upload the plugin and activate it.
2. Go to **Settings → Site Contextsnap**:
   - Enable CORS and list allowed origins (optional)
   - Set rate limit threshold/window and cache TTL
   - Toggle update metadata inclusion (optional)
   - Configure allow‑lists (caps, user IDs, IPs) (optional)
3. For HMAC access, generate a key id and secret on the settings page and add it to `wp-config.php`:
define('PCC_HMAC_KEYS_JSON', '{"my-key-id":"<secret>"}');
// or
define('PCC_HMAC_KEY_my_key_id', '<secret>');


== Frequently Asked Questions ==

= How do I authenticate? =

**Application Passwords** (recommended):


GET /wp-json/pcc/v1/snapshot
Authorization: Basic base64(<username>:<application-password>)


**HMAC‑signed** (no WordPress user):


GET /wp-json/pcc/v1/snapshot/signed
X-PCC-Key: <key id>
X-PCC-Timestamp: <unix seconds>
X-PCC-Signature: hex(hmac_sha256("<METHOD>\n<PATH>\n<TIMESTAMP>", secret))


= Does it expose PII or secrets? =

No. It only returns technical metadata. No secrets are read from the DB; HMAC secrets are only read from `wp-config.php`.

= Does it support multisite? =

Yes. It reports network‑active plugins and the `MULTISITE` flag.

= What changed with CORS wildcards in 1.2.0? =

CORS wildcard patterns like `https://*.example.com` now match **ONLY subdomains**, not the base domain. If you need both `example.com` and `sub.example.com`, add both patterns explicitly:

https://example.com
https://*.example.com


This improves security by preventing unintended base domain matching.

= Can I hide database version information? =

Yes. In **Settings → Site Contextsnap**, uncheck "Expose database version" to exclude database driver and version from the snapshot. This minimizes information disclosure.

== Screenshots ==

1. Settings page (CORS, rate limiting, caching, and update metadata).
2. HMAC keys page with one‑time secret display and copy buttons.

== Changelog ==

= 1.2.0 =
* Security: Added centralized Signature_Validator service with enhanced timestamp validation
* Security: Improved CORS wildcard validation with scheme enforcement
* Security: Enhanced timestamp validation (rejects leading zeros and out-of-range values)
* Security: Added rate limiting for OPTIONS requests to prevent CORS probing
* Feature: Added configurable database version exposure setting
* Feature: Added HTTP origin warning in settings page
* Breaking Change: CORS wildcard patterns (*.example.com) now match ONLY subdomains, not base domain
* Improved: Refactored HMAC validation into dedicated service (removed 30+ lines of duplicate code)
* Improved: Better code separation and testability

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.2.0 =
Security improvements and parity with Drupal module. BREAKING: CORS wildcard behavior changed - `*.example.com` now matches ONLY subdomains. Add both `https://example.com` and `https://*.example.com` if you need both.

= 1.0.0 =
Initial release with REST, HMAC route, caching, rate limiting, and WP‑CLI.

== Privacy Policy ==

This plugin exposes a read‑only technical snapshot and does not collect personal data or phone‑home. If you opt in to expose update metadata, only publicly available version information is included.
