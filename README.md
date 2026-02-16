# Project Context Connector

Project Context Connector exposes a safe, read-only JSON snapshot of your WordPress site for AI agents, automation scripts, and CI/CD pipelines. It reports core version, active plugins with versions, PHP and database versions, active theme details, and selected non-PII configuration flags. Routes are permission-gated, cached, and rate-limited.

## Why Use Project Context Connector?

Traditional WordPress sites are opaque to AI agents and automation tools. When building Slack bots, CI pipelines, or AI-powered assistants, there's no standard way to programmatically ask "What WordPress version is this site running?" or "Which plugins are installed and are any outdated?"

This plugin solves that by exposing a read-only JSON snapshot that AI agents and scripts can consume to understand your WordPress site's current state without requiring direct database access or file system inspection.

### Use Cases

- **AI-Powered Support**: ChatGPT, Claude, or other AI agents with real-time context about your WordPress site
- **DevOps Automation**: CI/CD scripts that adapt based on current site configuration and plugin versions
- **Slack/Teams Bots**: Team bots that answer "What version of PHP are we running in production?"
- **Multi-Site Management**: Centralized dashboards that aggregate metadata from multiple WordPress instances
- **Security Auditing**: Automated checks for outdated plugins or security vulnerabilities
- **Documentation Generation**: Auto-generate technical documentation that stays current with your site
- **Incident Response**: Quick environment snapshots for troubleshooting and support tickets

## Features

- **Read-only REST API endpoints** with a curated project snapshot
- **WP-CLI command** `wp pcc snapshot` that emits the same JSON for local use and pipelines
- **Security focused**: Permission-gated routes, no write endpoints, no remote code execution, no telemetry
- **Optional update status** derived from WordPress update checks only (no outbound requests)
- **Performance**: Cacheable responses with configurable TTL, built-in rate limiting with sliding window, optional CORS allow-list for browser clients
- **Quality**: PSR-4 autoloading, PHPDoc, translatable strings, accessible admin form that meets WCAG 2.2 AA
- **Compatible** with WordPress 6.0+ and PHP 8.0+
- **Optional HMAC signed endpoint** for token-based server-to-server access without WordPress user accounts

## Requirements

- WordPress 6.0 or later
- PHP 8.0, 8.1, 8.2, or 8.3
- Optional for authentication:
  - WordPress Application Passwords (core, WordPress 5.6+)
  - OAuth2 plugin if you prefer bearer tokens
- Recommended: WP-CLI for command-line access

## Installation

### From WordPress.org

1. Navigate to **Plugins → Add New** in WordPress admin
2. Search for "Project Context Connector"
3. Click **Install Now**, then **Activate**

### Manual Installation

```bash
cd wp-content/plugins
git clone https://github.com/yourusername/project-context-connector-wp.git project-context-connector
cd project-context-connector
composer install --no-dev
```

Then activate via WP-CLI or WordPress admin:

```bash
wp plugin activate project-context-connector
```

## Quick Start (5 Minutes)

### 1. Install the Plugin

```bash
wp plugin install project-context-connector --activate
```

### 2. Create Application Password

```bash
# Create a dedicated service user
wp user create pcc_bot pcc-bot@example.com --role=administrator

# Generate Application Password (WordPress 5.6+)
# Visit: /wp-admin/user-edit.php?user_id=<USER_ID>#application-passwords-section
# Or use a plugin like "Application Passwords" to generate via WP-CLI
```

**Alternative**: Use an existing admin user and create an Application Password in **Users → Profile → Application Passwords**.

### 3. Test the Endpoint

```bash
curl -u username:application-password \
  -H "Accept: application/json" \
  https://your-site.com/wp-json/pcc/v1/snapshot | jq .
```

### 4. Integrate with Your AI Agent

Add the endpoint to your AI agent's tools, MCP server configuration, or automation scripts. See the [AI Agent Integration](#ai-agent-integration) section for examples.

## Configuration

Navigate to **Settings → Project Context Connector** (`/wp-admin/options-general.php?page=project-context-connector`) to configure:

- **Enable CORS headers**: Toggle CORS support on/off
- **Allowed origins**: CORS allow-list for browser clients (one origin per line)
- **Rate limit: requests per window**: Maximum requests per window (default: 60)
- **Rate limit window (seconds)**: Time window in seconds (default: 60)
- **Cache max age (seconds)**: Cache duration (default: 300)
- **Expose update metadata**: Include update availability per plugin/theme
- **Expose database version**: Include database driver and version (disable for minimal information disclosure)
- **Allow capabilities**: Additional capabilities that grant access (beyond `pcc_read_snapshot`)
- **Allow user IDs**: Specific user IDs that have access
- **Allow IPs**: IP address allow-list for additional security
- **Allow Bearer auth**: Enable OAuth2 bearer token support

## Endpoints

### Standard Endpoint (Permission-Gated)

```
GET /wp-json/pcc/v1/snapshot
```

**Authentication**: Requires `pcc_read_snapshot` capability (mapped to `manage_options` by default) via:
- Application Passwords (WordPress 5.6+)
- OAuth2 bearer token (if an OAuth2 plugin is installed)
- WordPress session cookie (for admin use only)

**Response**: JSON snapshot with `Cache-Control` headers

### Signed Endpoint (HMAC Authentication)

```
GET /wp-json/pcc/v1/snapshot/signed
```

**Authentication**: HMAC signature with shared secret (no WordPress user required)

**Response**: Same JSON structure as standard endpoint

Both endpoints return identical JSON structures and apply the same rate limiting and caching policies.

## Authentication Methods

### Option A: Application Passwords (Easiest)

WordPress 5.6+ includes Application Passwords natively. Create one for a dedicated service user:

1. Create user: `wp user create pcc_bot pcc-bot@example.com --role=administrator`
2. Navigate to **Users → Profile** for that user
3. Scroll to **Application Passwords** section
4. Enter name (e.g., "CI Pipeline") and click **Add New Application Password**
5. Copy the generated password (spaces are optional)

Example request:

```bash
curl -u pcc_bot:xxxx-xxxx-xxxx-xxxx-xxxx-xxxx \
  -H "Accept: application/json" \
  https://your-site.com/wp-json/pcc/v1/snapshot
```

### Option B: HMAC Signed Requests (Best for Production)

Add a shared secret in `wp-config.php`:

```php
// Option 1: JSON format (supports multiple keys)
define('PCC_HMAC_KEYS_JSON', '{"production-bot":"paste-a-strong-random-secret-here"}');

// Option 2: Individual key constants
define('PCC_HMAC_KEY_production_bot', 'paste-a-strong-random-secret-here');
```

**Generate a secure secret**:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Call the signed route with HMAC headers:

```
GET /wp-json/pcc/v1/snapshot/signed
Headers:
  X-PCC-Key: production-bot
  X-PCC-Timestamp: <unix seconds>
  X-PCC-Signature: hex(hmac_sha256("<METHOD>\n<PATH>\n<TIMESTAMP>", secret))
```

**Signature Construction**:

```bash
# Example: Generate signature for GET request
METHOD="GET"
PATH="/wp-json/pcc/v1/snapshot/signed"
TIMESTAMP=$(date +%s)
SECRET="your-secret-key"

# Canonical string: METHOD + newline + PATH + newline + TIMESTAMP
CANONICAL="${METHOD}\n${PATH}\n${TIMESTAMP}"

# Generate HMAC-SHA256 signature
SIGNATURE=$(echo -n -e "$CANONICAL" | openssl dgst -sha256 -hmac "$SECRET" | cut -d' ' -f2)

# Make request
curl -H "X-PCC-Key: production-bot" \
     -H "X-PCC-Timestamp: $TIMESTAMP" \
     -H "X-PCC-Signature: $SIGNATURE" \
     https://your-site.com/wp-json/pcc/v1/snapshot/signed
```

Preflight `OPTIONS` requests are allowed without signatures. The signed route applies the same rate limiting as the standard route.

### Option C: OAuth2 Bearer Tokens

Install and configure an OAuth2 plugin (e.g., WP OAuth Server), then use bearer tokens:

```bash
curl -H "Authorization: Bearer YOUR_OAUTH_TOKEN" \
     -H "Accept: application/json" \
     https://your-site.com/wp-json/pcc/v1/snapshot
```

## Authentication Methods Compared

| Feature | Application Passwords | HMAC Signed | OAuth2 | WP Session |
|---------|----------------------|-------------|---------|------------|
| Setup complexity | Easy | Medium | Hard | Easy |
| Credentials in logs | Yes (base64) | No | No | Cookie only |
| Requires WP user | Yes | No | Yes | Yes |
| Suitable for CI/CD | Yes | Yes (best) | Yes | No |
| Browser-friendly | Yes | No | Yes | Yes |
| Rotating credentials | Revoke/create new | Update wp-config.php | Refresh token | N/A |
| Best for | Development, testing | Production automation | API integration | Admin use only |

**Recommendation**: Use HMAC for production automation, Application Passwords for development and testing, OAuth2 for third-party integrations.

## WP-CLI Command

```bash
# Output JSON to stdout
wp pcc snapshot

# Pretty-printed JSON
wp pcc snapshot --pretty

# Save to file (useful in CI/CD)
wp pcc snapshot --pretty > snapshot.json
```

Use this in CI pipelines to archive build artifacts with environment facts, or for local debugging without HTTP requests.

## Example Response

```json
{
  "generated_at": "2026-02-16T12:00:00+00:00",
  "site": {
    "home_url": "https://example.com/",
    "site_url": "https://example.com/",
    "multisite": false
  },
  "environment": {
    "wp_version": "6.7.1",
    "php_version": "8.3.0",
    "db_server": "MySQL",
    "db_version": "8.0.36",
    "environment_type": "production",
    "locale": "en_US"
  },
  "flags": {
    "WP_DEBUG": false,
    "SCRIPT_DEBUG": false,
    "DISALLOW_FILE_EDIT": true,
    "MULTISITE": false,
    "WP_CACHE": true,
    "WP_ENVIRONMENT_TYPE": "production"
  },
  "theme": {
    "name": "Twenty Twenty-Four",
    "version": "1.0",
    "stylesheet": "twentytwentyfour",
    "template": "twentytwentyfour",
    "is_child": false,
    "update_available": false,
    "update": {}
  },
  "plugins": {
    "active": [
      {
        "name": "Project Context Connector",
        "version": "1.1.0",
        "plugin_file": "project-context-connector/project-context-connector.php",
        "network_active": false,
        "update_available": false,
        "update": {}
      }
    ],
    "mu": []
  },
  "updates": {
    "core": {},
    "plugins": {},
    "themes": {}
  }
}
```

## Caching and Rate Limiting

### Caching

- Responses include explicit `Cache-Control: max-age=<TTL>` header (configurable, default 300s)
- Internal caching uses WordPress Transients API for fast repeated access
- Cache is automatically purged when plugins/themes are activated, deactivated, or updated
- Manually clear cache: `wp transient delete pcc_snapshot` or flush all caches

### Rate Limiting

- Throttling uses a sliding window algorithm with Redis-like precision
- Authenticated users are tracked by user ID, anonymous by IP address
- On `429 Too Many Requests`, a `Retry-After` header indicates wait time
- OPTIONS preflight requests are rate-limited to prevent CORS probing attacks
- Configure threshold and window in plugin settings

## Security Best Practices

### For Production Environments

1. **Always use HTTPS** - Never expose this endpoint over unencrypted HTTP
2. **Prefer HMAC over Application Passwords** - Keeps credentials out of web server access logs
3. **Restrict CORS origins** - Use exact domain matches; avoid wildcards in production
4. **Enable rate limiting** - Default (60 req/min) is reasonable; adjust based on usage patterns
5. **Rotate secrets regularly** - Change HMAC secrets every 90 days minimum
6. **Monitor access logs** - Watch for unusual access patterns or brute force attempts
7. **Keep WordPress updated** - This plugin relies on core security; stay current with security releases
8. **Minimize information disclosure** - Disable database version exposure if not needed

### Secret Management

**Generate strong secrets** (32+ characters, high entropy):

```bash
# Linux/macOS
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Alternative with OpenSSL
openssl rand -hex 32
```

**Store secrets securely**:

```php
// wp-config.php - Never commit secrets to version control
define('PCC_HMAC_KEY_production_bot', getenv('PCC_SECRET')); // From environment variable
define('PCC_HMAC_KEY_ci_pipeline', getenv('PCC_CI_SECRET'));
```

**Secret rotation procedure**:

1. Generate new secret
2. Add new key with different key ID to `wp-config.php`
3. Update client to use new key ID and secret
4. Verify new key works
5. Remove old key from `wp-config.php` after 24-48 hours

**What to do if a secret is compromised**:

1. Immediately remove the compromised key from `wp-config.php`
2. Clear all caches: `wp cache flush`
3. Review access logs for unauthorized access
4. Generate and deploy new secrets to all legitimate clients
5. Consider temporarily disabling the signed endpoint if breach is severe

### What's Exposed vs. Not Exposed

**Information Exposed**:
- WordPress core version
- PHP version
- Database driver and version (if enabled)
- Active plugin names, versions, and files
- Update availability per plugin/theme (if enabled)
- Active theme name and version
- Configuration flags: WP_DEBUG, SCRIPT_DEBUG, DISALLOW_FILE_EDIT, WP_CACHE, environment type
- Must-use plugins (if any)

**Information NOT Exposed**:
- User emails, passwords, or any PII
- API keys, tokens, or credentials
- Post/page content or custom post types
- Database credentials or connection strings
- Absolute file system paths (only relative plugin paths)
- Environment variables
- Private files or secrets
- No telemetry or tracking

This plugin is designed to expose only operational metadata useful for automation and support, never sensitive data.

### CORS Security

CORS is opt-in and requires explicit origin allow-listing. Configure carefully:

- **Exact matches**: `https://example.com` matches only that exact origin
- **Wildcard subdomains**: `https://*.example.com` matches subdomains ONLY, not `example.com` itself
  - To match both, add both: `https://example.com` and `https://*.example.com`
- **HTTP warnings**: The admin form warns when HTTP origins are configured (insecure for production)
- **Scheme enforcement**: Wildcard patterns respect schemes (http vs https)

**Production recommendation**: Disable CORS unless absolutely required. Use HMAC signed endpoint for server-to-server access instead.

## AI Agent Integration

### Claude/ChatGPT Integration

Add this to your AI agent's system prompt or tool configuration:

```
You have access to WordPress site metadata at https://example.com/wp-json/pcc/v1/snapshot

Authentication: Application Password
  Username: pcc_bot
  Password: [from secure storage]

Fetch this endpoint to understand:
- WordPress core version and PHP version
- Active plugins with versions and update status
- Current theme and site configuration
- Database driver and version

Use this context when answering questions about the WordPress site or suggesting plugin
installations. Always check update_available field to warn about outdated plugins.
```

### Model Context Protocol (MCP) Server

Build an MCP server wrapper for use with Claude Desktop or other MCP clients:

```javascript
// Example MCP server tool definition
{
  "name": "get_wordpress_context",
  "description": "Fetch current WordPress site context including plugins, versions, and configuration",
  "inputSchema": {
    "type": "object",
    "properties": {
      "site_url": {
        "type": "string",
        "description": "Base URL of the WordPress site"
      }
    },
    "required": ["site_url"]
  }
}
```

See [Model Context Protocol documentation](https://modelcontextprotocol.io/) for implementation details.

### Slack Bot Example

```javascript
// Slack slash command: /site-info
const { WebClient } = require('@slack/web-api');
const fetch = require('node-fetch');

async function handleSiteInfo() {
  const snapshot = await fetch('https://example.com/wp-json/pcc/v1/snapshot', {
    headers: {
      'Authorization': `Basic ${Buffer.from('pcc_bot:password').toString('base64')}`,
      'Accept': 'application/json'
    }
  }).then(r => r.json());

  const outdatedPlugins = snapshot.plugins.active.filter(
    p => p.update_available
  );

  return {
    response_type: 'in_channel',
    text: `Running WordPress ${snapshot.environment.wp_version} with PHP ${snapshot.environment.php_version}`,
    blocks: [
      {
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: `*Environment Info*\n• WordPress: ${snapshot.environment.wp_version}\n• PHP: ${snapshot.environment.php_version}\n• Database: ${snapshot.environment.db_server} ${snapshot.environment.db_version}`
        }
      },
      {
        type: 'section',
        text: {
          type: 'mrkdwn',
          text: `*Plugins*\n${snapshot.plugins.active.length} active plugins${outdatedPlugins.length > 0 ? `\n:warning: ${outdatedPlugins.length} plugins have updates available!` : ''}`
        }
      }
    ]
  };
}
```

### Python Automation Example

```python
import requests
import json

class WordPressContextClient:
    def __init__(self, base_url, username, password):
        self.base_url = base_url
        self.auth = (username, password)

    def get_snapshot(self):
        response = requests.get(
            f"{self.base_url}/wp-json/pcc/v1/snapshot",
            auth=self.auth,
            headers={"Accept": "application/json"}
        )
        response.raise_for_status()
        return response.json()

    def check_updates(self):
        snapshot = self.get_snapshot()
        outdated = [
            p for p in snapshot['plugins']['active']
            if p['update_available']
        ]
        return outdated

# Usage in CI/CD pipeline
client = WordPressContextClient('https://example.com', 'pcc_bot', 'password')
outdated_plugins = client.check_updates()

if outdated_plugins:
    print(f"WARNING: {len(outdated_plugins)} plugins have updates available!")
    for plugin in outdated_plugins:
        print(f"  - {plugin['name']} ({plugin['version']})")
```

## Troubleshooting

### 403 Forbidden

**Causes**:
- User lacks `pcc_read_snapshot` capability (default: `manage_options`)
- Application Password not configured or incorrect
- CORS origin not in allow-list (for browser requests)
- HMAC signature validation failed (signed endpoint)

**Solutions**:
```bash
# Check user capabilities
wp user get pcc_bot --field=capabilities

# Verify plugin is active
wp plugin status project-context-connector

# Test with Application Password
curl -u username:app-password https://your-site.com/wp-json/pcc/v1/snapshot
```

### 429 Too Many Requests

**Cause**: Rate limit exceeded

**Solution**: Wait for the duration specified in `Retry-After` header, then retry. Or adjust rate limits in plugin settings.

```bash
# Check current settings via WP-CLI
wp option get pcc_options
```

### Empty or Missing Plugin Versions

**Causes**:
- Plugin not in standard format (missing header comments)
- Development version without version number

**Solutions**:
- Ensure plugin files have standard WordPress plugin headers
- Check `wp-content/plugins/<plugin>/plugin-name.php` for `Version:` header

### Signature Validation Failing (HMAC)

**Common issues**:
1. **Clock skew**: Server and client clocks differ by >5 minutes
2. **Wrong secret**: Secret in `wp-config.php` doesn't match client
3. **Incorrect signature construction**: Newlines, encoding, or hash algorithm mismatch

**Debug checklist**:
```bash
# Verify server time
date +%s

# Check wp-config.php has correct key
wp eval "echo defined('PCC_HMAC_KEY_production_bot') ? 'Defined' : 'Not defined';"

# Test with verbose curl
curl -v -H "X-PCC-Key: your-key" \
     -H "X-PCC-Timestamp: $(date +%s)" \
     -H "X-PCC-Signature: your-signature" \
     https://your-site.com/wp-json/pcc/v1/snapshot/signed
```

**Signature construction must use**:
- Method: Uppercase (e.g., `GET`)
- Path: Without query string (e.g., `/wp-json/pcc/v1/snapshot/signed`)
- Timestamp: Unix seconds as string
- Canonical format: `<METHOD>\n<PATH>\n<TIMESTAMP>` (literal newlines)
- Algorithm: HMAC-SHA256
- Output: Lowercase hexadecimal

### Database Version Shows Empty String

**Cause**: `expose_database_version` setting is disabled

**Solution**:
```bash
# Enable via WP-CLI
wp option patch update pcc_options expose_database_version 1

# Or enable in admin: Settings → Project Context Connector
```

### Stale Data in Response

**Cause**: Response is cached

**Solution**:
```bash
# Clear transient cache
wp transient delete pcc_snapshot

# Or flush all caches
wp cache flush
```

## Testing

Run tests using WordPress core test suite:

```bash
# Install WordPress test environment
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

# Run PHPUnit tests
phpunit

# Run with coverage
phpunit --coverage-html coverage/
```

## Internationalization and Accessibility

- All UI strings use `esc_html__()` and `esc_html_e()` for translation
- Admin form meets WCAG 2.2 AA standards with proper labels, descriptions, and keyboard navigation
- Form validation provides clear, translatable error messages
- Translation-ready with `.pot` file in `languages/`

## Support and Contributions

- **GitHub Issues**: [Project Repository Issues](https://github.com/yourusername/project-context-connector-wp/issues)
- **Contributing**: See `CONTRIBUTING.md` for coding standards and pull request process
- **Security issues**: See `SECURITY.md` for responsible disclosure

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for detailed version history.

## Roadmap

Potential future enhancements:

- Webhook notifications when updates become available
- Historical snapshot storage for trend analysis
- Plugin compatibility checking API
- GraphQL endpoint alternative
- Performance metrics (page load times, cache hit rates)
- WooCommerce-specific metadata (if installed)
- Multisite network-wide snapshots

Suggest features in GitHub Issues!

## License

GPL-2.0-or-later. See [LICENSE](LICENSE) or [GNU GPL v2](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits

Developed and maintained by the WordPress community. See commit history for contributors.
