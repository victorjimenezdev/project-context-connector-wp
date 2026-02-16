# Security Policy

## Supported Versions

The plugin follows semantic versioning. Only the latest minor versions within each supported major will receive security fixes.

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## Reporting a Vulnerability

Please **do not** open public issues for security problems.

### Reporting Process

1. **Private disclosure**: Email the maintainers or use GitHub Security Advisories (if available)
2. **WordPress.org**: Report via the WordPress.org plugin security team if the plugin is listed on WordPress.org
3. **Required information**:
   - Vulnerability description
   - Proof of concept or reproduction steps
   - Affected versions
   - Impact assessment (severity, exploitability)
   - Suggested remediation (if known)

### Response Timeline

- **Acknowledgment**: Within 5 business days
- **Initial assessment**: Within 10 business days
- **Fix timeline**: Varies by severity (critical: days, high: weeks, medium/low: next release)
- **Disclosure**: Coordinated disclosure after fix is available

## Data Handling and Privacy

### Information Exposed

The plugin exposes **only non-PII operational metadata**:

- WordPress core version
- PHP version
- Database driver and version (configurable, can be disabled)
- Active plugin names, versions, and files
- Update availability per plugin/theme (if enabled)
- Active theme name and version
- Must-use plugins (if any)
- Configuration flags: WP_DEBUG, SCRIPT_DEBUG, DISALLOW_FILE_EDIT, WP_CACHE, environment type
- Relative plugin paths

### Information NOT Exposed

The plugin is explicitly designed **never** to expose:

- User emails, passwords, or any personally identifiable information (PII)
- API keys, tokens, or credentials
- Post/page content or custom post types
- Database credentials or connection strings
- Absolute file system paths
- Environment variables
- Private files or secrets
- No telemetry, tracking, or external requests

## Threat Model and Mitigations

### Threat: Unauthorized Information Disclosure

**Mitigation**:
- Permission-gated endpoints require explicit `pcc_read_snapshot` capability (default: `manage_options`)
- HMAC signed endpoint requires shared secret stored in `wp-config.php`
- Rate limiting prevents brute force attempts
- No sensitive data in responses

### Threat: Denial of Service (DoS)

**Mitigation**:
- Sliding window rate limiting per IP/user ID
- Configurable request throttling (default: 60 requests/minute)
- Cached responses reduce backend load (WordPress Transients API)
- OPTIONS requests rate-limited to prevent CORS probing

### Threat: Cross-Site Request Forgery (CSRF)

**Mitigation**:
- Read-only endpoints only (no state changes)
- No CSRF protection needed for GET requests
- Signed endpoint uses HMAC with timestamp to prevent replay attacks

### Threat: Timing Attacks

**Mitigation**:
- `hash_equals()` used for signature comparison (constant-time)
- Multiple validation failures indistinguishable to attacker

### Threat: Replay Attacks (HMAC)

**Mitigation**:
- Timestamp included in signature with configurable skew window (default: 5 minutes)
- Expired requests rejected
- Enhanced timestamp validation prevents leading zero and out-of-range timestamps

## Secret Management Best Practices

### Generating Secrets

Always use cryptographically secure random generation:

```bash
# PHP (recommended, 32 bytes = 256 bits)
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# OpenSSL alternative
openssl rand -hex 32

# Node.js
node -e "console.log(require('crypto').randomBytes(32).toString('hex'))"
```

**Minimum requirements**:
- Length: 32+ characters (256+ bits of entropy)
- Character set: Hexadecimal, base64, or alphanumeric with symbols
- Generation: Cryptographically secure random number generator (CSRNG)

### Storing Secrets

**Never** commit secrets to version control. Use environment variables:

```php
// wp-config.php
define('PCC_HMAC_KEY_production_bot', getenv('PCC_SECRET'));
define('PCC_HMAC_KEY_ci_pipeline', getenv('PCC_CI_SECRET'));
```

**Environment variable options**:
- `.env` files (with proper `.gitignore`)
- Server environment variables
- Secrets management services (Vault, AWS Secrets Manager, etc.)
- Managed WordPress host secrets (WP Engine, Kinsta, etc.)

**Development vs. Production**:
- Development: Can use simpler secrets, documented in team wiki
- Staging: Use production-grade secrets, separate from production
- Production: High-entropy secrets from secure source, regularly rotated

### Secret Rotation

Rotate secrets regularly (recommended: every 90 days):

1. **Generate new secret**:
   ```bash
   NEW_SECRET=$(php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;")
   ```

2. **Add new key** with different key ID:
   ```php
   // wp-config.php
   define('PCC_HMAC_KEY_bot_2026_02', 'new-secret-here');  // New
   define('PCC_HMAC_KEY_bot_2025_11', 'old-secret-here');  // Keep temporarily
   ```

3. **Update clients** to use new key ID and secret

4. **Verify** new key works in production

5. **Remove old key** after 24-48 hours (grace period for in-flight requests)

6. **Clear caches**: `wp cache flush` and `wp transient delete pcc_snapshot`

### Compromised Secret Response

If a secret is compromised or suspected compromised:

1. **Immediate action**:
   ```php
   // Remove from wp-config.php
   // define('PCC_HMAC_KEY_compromised_key', 'secret');  // REMOVED/COMMENTED
   ```

2. **Clear all caches**: `wp cache flush` and `wp transient delete pcc_snapshot`

3. **Review access logs**:
   ```bash
   # Look for unauthorized access
   grep "wp-json/pcc/v1" /var/log/nginx/access.log
   grep "X-PCC-Key: compromised-key" /var/log/nginx/access.log
   ```

4. **Generate and deploy new secrets** to all legitimate clients

5. **Consider temporary disable** if breach is severe:
   ```php
   // Temporarily disable all HMAC keys
   // Comment out all PCC_HMAC_KEY_* constants
   ```

6. **Document incident** for security audit trail

7. **Inform WordPress.org security team** if plugin is listed

## CORS Security

CORS is **opt-in** and requires explicit origin allow-listing.

### Configuration Best Practices

**Exact matches** (recommended for production):
```
https://dashboard.example.com
https://admin.example.com
```

**Wildcard subdomains** (use cautiously):
```
https://*.example.com  # Matches subdomains ONLY
https://example.com     # Add base domain separately if needed
```

**Important**:
- Wildcard `*.example.com` matches `sub.example.com` but **not** `example.com`
- HTTP origins trigger security warnings (disable in production)
- Scheme enforcement: `https://*.example.com` won't match `http://sub.example.com`

### CORS Attack Prevention

- **Probing attacks**: OPTIONS requests are rate-limited
- **Credential theft**: No credentials in responses; CORS headers only for allow-listed origins
- **Information disclosure**: `Vary: Origin` header prevents cross-origin cache poisoning

### Production Recommendation

**Disable CORS** unless browser access is required. Use HMAC signed endpoint for server-to-server access instead:

- HMAC requires no CORS (server-to-server)
- Application Passwords work without CORS (curl, scripts)
- CORS only needed for in-browser JavaScript

## Authentication Security

### Application Passwords

**Pros**:
- Simple setup
- Built into WordPress 5.6+
- Revocable per-application
- Standard HTTP authentication

**Cons**:
- Credentials in every request (base64-encoded, not encrypted)
- Visible in web server logs
- Requires WordPress user account

**Best practices**:
1. Use HTTPS always (credentials in clear over HTTP)
2. Create dedicated service user with minimal permissions
3. Use Application Passwords, not user passwords
4. Revoke unused Application Passwords regularly
5. Never reuse WordPress admin credentials

**Example secure user creation**:
```bash
# Create service user
wp user create pcc_bot pcc-bot@example.com --role=administrator

# Create Application Password via WordPress admin:
# Users → Profile → Application Passwords section
# Name: "CI Pipeline" or "AI Agent"

# Test the password
curl -u pcc_bot:xxxx-xxxx-xxxx-xxxx \
  https://your-site.com/wp-json/pcc/v1/snapshot
```

### HMAC Signed Requests

**Pros**:
- No WordPress user required
- Credentials not in request (only signature)
- Timestamp prevents replay attacks
- Multiple keys with different IDs

**Cons**:
- Complex client implementation
- Clock synchronization required
- Not browser-friendly

**Best practices**:
1. Generate high-entropy secrets (256+ bits)
2. Store secrets in environment variables, not `wp-config.php` directly
3. Use key IDs for rotation (`bot_2026_02`, `bot_2026_05`)
4. Monitor for signature validation failures (potential attack)
5. Implement client-side retry with backoff for 429 responses

**Security parameters**:
- Timestamp skew: Default 300s (5 minutes), configurable
- Timestamp validation: Rejects leading zeros, out-of-range values (2000-2100)
- Signature algorithm: HMAC-SHA256 (not SHA1 or MD5)
- Canonical format: `METHOD\nPATH\nTIMESTAMP` (exact format required)
- Timing-safe comparison: `hash_equals()` prevents timing attacks

## Rate Limiting

### Configuration

Default: 60 requests per 60-second window per IP/user ID

**Tuning recommendations**:
- **Development**: 120+ (generous for testing)
- **Production**: 60 (reasonable for automation)
- **High-traffic**: 120-300 (with monitoring)
- **Public endpoint**: 30 (more restrictive)

### Monitoring

Watch for rate limit violations via WordPress admin or WP-CLI:

```bash
# Check plugin options
wp option get pcc_options

# Monitor access logs
tail -f /var/log/nginx/access.log | grep "wp-json/pcc/v1"
```

**Red flags**:
- Single IP hitting limit repeatedly (potential DDoS)
- Multiple IPs hitting limit (distributed attack)
- Sudden spike in 429 responses (investigate cause)

## Additional Security Recommendations

### HTTPS Configuration

Always use HTTPS in production:

```apache
# Apache: Enforce HTTPS
<Location /wp-json/pcc>
    Require expr %{HTTPS} == "on"
</Location>
```

```nginx
# Nginx: Redirect HTTP to HTTPS
if ($scheme = http) {
    return 301 https://$server_name$request_uri;
}
```

### Web Application Firewall (WAF)

Consider WAF rules for additional protection:

- Block requests with suspicious User-Agents
- Geo-blocking if only specific regions need access
- Additional rate limiting at WAF level
- Cloudflare, Sucuri, or Wordfence rules

### Monitoring and Alerting

Set up alerts for:

- Repeated 403 errors (unauthorized access attempts)
- Repeated 429 errors (rate limit violations)
- Sudden traffic spikes to `/wp-json/pcc/v1/*`
- Signature validation failures (HMAC)

### Security Headers

Consider additional security headers at web server level:

```
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
Permissions-Policy: geolocation=(), microphone=(), camera=()
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### WordPress Security

Keep WordPress core and plugins updated:

```bash
# Check for updates
wp core check-update
wp plugin list --update=available

# Update WordPress core
wp core update
wp core update-db

# Update plugins
wp plugin update --all

# Security-only updates (if available via plugin)
wp plugin update project-context-connector
```

### File Permissions

Ensure proper file permissions:

```bash
# wp-config.php should be read-only
chmod 600 wp-config.php

# Plugin files should not be writable by web server
find wp-content/plugins/project-context-connector -type f -exec chmod 644 {} \;
find wp-content/plugins/project-context-connector -type d -exec chmod 755 {} \;
```

## Compliance Considerations

### GDPR

The plugin does **not** expose personal data. However:

- Access logs may contain IP addresses (personal data under GDPR)
- Ensure access logs comply with retention policies
- Document this endpoint in privacy policy if exposed to EU users
- Application Passwords contain user association (not exposed in responses)

### SOC 2 / ISO 27001

For compliance:

- Document authentication and authorization mechanisms
- Implement secret rotation procedures
- Maintain audit logs of access
- Regular security reviews and penetration testing
- Data flow diagrams showing what's exposed

### HIPAA / PCI DSS

If site handles sensitive data:

- Disable database version exposure
- Restrict access via IP allow-lists
- Use HMAC authentication only (no Application Passwords)
- Monitor all access
- Regular security audits

## Security Changelog

Document security-relevant changes in releases:

- **1.1.0**: Added centralized Signature_Validator service, improved CORS wildcard validation with scheme enforcement, enhanced timestamp validation (leading zero rejection, sanity checks), rate limiting for OPTIONS requests
- **1.0.0**: Initial release with permission-gated and HMAC endpoints

## Security Features by Version

### Version 1.1.0

**New security features**:
- Centralized `Signature_Validator` service with enhanced timestamp validation
- Leading zero rejection in timestamps (prevents bypasses)
- Timestamp sanity checks (2000-2100 range)
- CORS wildcard subdomain matching with scheme enforcement
- OPTIONS request rate limiting (prevents CORS probing)
- Configurable database version exposure

**Breaking changes**:
- CORS wildcard patterns (`*.example.com`) now match ONLY subdomains, not base domain

### Version 1.0.0

**Initial security features**:
- Permission-gated REST API endpoint
- HMAC-SHA256 signed endpoint
- Rate limiting (sliding window)
- CORS with origin allow-listing
- Transient-based caching
- No PII exposure

## Best Practices Summary

1. **Always use HTTPS** in production
2. **Prefer HMAC over Application Passwords** for automation
3. **Disable CORS** unless browser access required
4. **Enable rate limiting** (default is reasonable)
5. **Rotate secrets every 90 days** minimum
6. **Monitor access logs** for suspicious activity
7. **Keep WordPress updated** with security releases
8. **Disable database version** exposure if not needed
9. **Use IP allow-lists** for additional security
10. **Review permissions** regularly (who has `pcc_read_snapshot`)

## Testing Security

### Penetration Testing Checklist

- [ ] Test authentication bypass attempts
- [ ] Test HMAC signature validation (wrong signature, expired timestamp, replay)
- [ ] Test rate limiting (exceed threshold, verify 429 response)
- [ ] Test CORS enforcement (unauthorized origins)
- [ ] Test HTTPS enforcement (if configured)
- [ ] Test information disclosure (verify no PII in responses)
- [ ] Test timing attacks (signature comparison)
- [ ] Test DOS resistance (high request volume)

### Security Scanning

Run security scanners:

```bash
# WP-CLI security check
wp plugin verify-checksums project-context-connector

# Vulnerability scanning (if available)
wp vulnerability scan

# Static analysis (for developers)
phpstan analyse includes/
```

## Contact

For security concerns:
- GitHub Security Advisories: [Repository URL]
- WordPress.org plugin security: security@wordpress.org
- Direct contact: [Maintainer email]

For general support:
- GitHub Issues: [Repository URL]
- WordPress.org support forums

---

**Last updated**: 2026-02-16
**Version**: 1.1.0
