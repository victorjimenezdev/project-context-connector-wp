# WordPress Plugin Upgrade to 1.1.0 - Implementation Plan

This document outlines all changes needed to bring the WordPress plugin to feature and security parity with the Drupal 1.1.0 release.

## Security Improvements (Critical)

### 1. Signature Validator Refactoring
**Status**: ✅ DONE - `includes/services/class-signature-validator.php` created

**Next Steps**:
- Update `includes/rest/class-snapshot-rest-controller.php` to use the new `Signature_Validator` service
- Remove duplicate HMAC logic from lines 145-170
- Update `includes/class-plugin.php` to instantiate the new service

**Benefits**:
- Enhanced timestamp validation (leading zeros, sanity checks)
- Centralized signature logic for easier maintenance
- Better testability

### 2. CORS Validation Improvements
**File**: `includes/services/class-cors-manager.php`

**Changes Needed**:
```php
// Current: Line 59 - Exact match only
if ( $origin && in_array( $origin, $allowed_origins, true ) ) {

// New: Add wildcard subdomain matching with scheme enforcement
private function matches_origin( $origin, $pattern ) {
    $normalized_origin = rtrim( $origin, '/' );
    $normalized_pattern = rtrim( $pattern, '/' );

    // Exact match.
    if ( 0 === strcasecmp( $normalized_origin, $normalized_pattern ) ) {
        return true;
    }

    // Wildcard subdomain: "*.example.com" or "https://*.example.com".
    if ( 0 === strpos( $normalized_pattern, '*.' ) ||
         0 === strpos( $normalized_pattern, 'https://*.' ) ||
         0 === strpos( $normalized_pattern, 'http://*.' ) ) {

        // Extract scheme and host from origin.
        $origin_scheme = wp_parse_url( $normalized_origin, PHP_URL_SCHEME );
        $origin_host = wp_parse_url( $normalized_origin, PHP_URL_HOST );

        // Extract scheme and host from pattern.
        $pattern_scheme = wp_parse_url( $normalized_pattern, PHP_URL_SCHEME );
        if ( null === $pattern_scheme && 0 === strpos( $normalized_pattern, '*.' ) ) {
            $pattern_scheme = 'https'; // Default to https.
        }

        $pattern_host = preg_replace( '/^\w+:\/\//', '', $normalized_pattern );
        $pattern_host = ltrim( $pattern_host, '*.' );

        // Ensure schemes match (security).
        if ( $origin_scheme !== $pattern_scheme ) {
            return false;
        }

        // Wildcard should match ONLY subdomains, not base domain.
        return $origin_host !== '' &&
               $origin_host !== $pattern_host &&
               0 === substr_compare( strtolower( $origin_host ), '.' . strtolower( $pattern_host ),
                                    -strlen( '.' . $pattern_host ) );
    }

    return false;
}

// Update line 59 to:
foreach ( $allowed_origins as $pattern ) {
    if ( $this->matches_origin( $origin, $pattern ) ) {
        // Send CORS headers
        break;
    }
}
```

**Breaking Change**: Wildcard `*.example.com` now matches ONLY subdomains, not base domain.

### 3. Rate Limiting for OPTIONS Requests
**File**: `includes/rest/class-snapshot-rest-controller.php`

**Current**: OPTIONS requests bypass rate limiting (line 68 in CORS_Manager)

**Change**: Apply rate limiting to OPTIONS in `maybe_throttle_request()` before CORS handling.

### 4. Database Version Configuration
**Files**:
- `includes/admin/class-settings-page.php` - Add checkbox
- `includes/services/class-snapshot-builder.php` - Make DB version conditional

**New Option**: `expose_database_version` (boolean, default true)

## Documentation Improvements

### 1. README.md (New File)
Create comprehensive README.md (similar to Drupal version) with:
- "Why Use This?" section
- Use cases (AI agents, DevOps, Slack bots)
- Quick Start guide (5 minutes)
- Authentication methods comparison table
- Security best practices
- AI integration examples (Claude, ChatGPT, Slack, Python)
- Troubleshooting section
- 700+ lines matching Drupal quality

### 2. SECURITY.md Enhancement
Expand current 8-line file to match Drupal version:
- Threat model with mitigations
- Secret management procedures
- Rotation workflows
- Compromised secret response
- CORS security configuration
- Compliance considerations (GDPR, SOC 2)

### 3. readme.txt Update
**File**: `readme.txt` (WordPress.org standard)

**Changes**:
- Update "Tested up to" to 6.7
- Change "Stable tag" to 1.1.0
- Add changelog for 1.1.0
- Add FAQ about new security features
- Mention wildcard CORS behavior change

### 4. CHANGELOG.md (New File)
Create changelog matching Drupal format:
- [1.1.0] section with Added, Changed, Fixed, Security categories
- Breaking change documentation
- Link structure for releases

## Code Quality Improvements

### 1. Settings Page Enhancements
**File**: `includes/admin/class-settings-page.php`

**Add**:
- HTTP origin warning (similar to Drupal)
- Description text for all fields
- `expose_database_version` checkbox
- Validation for wildcard patterns

### 2. Plugin Class Updates
**File**: `includes/class-plugin.php`

**Add**:
- Instantiate `Signature_Validator` service
- Pass to REST controller constructor

### 3. PHPDoc and Type Hints
Review all service classes for:
- Complete PHPDoc blocks
- Type hints where possible (PHP 7.4+)
- Proper @package tags

## Testing

### 1. Unit Tests to Add
**New**: `tests/test-signature-validator.php`
- Test valid signatures
- Test invalid signatures
- Test timestamp validation edge cases
- Test leading zero rejection

### 2. Integration Tests to Update
**File**: `tests/test-rest-api.php`
- Add CORS wildcard matching tests
- Add OPTIONS rate limiting tests

## Version Updates

### Files to Update with 1.1.0:
1. `project-context-connector.php` - Line 5 (Version) and Line 22 (PCC_VERSION)
2. `readme.txt` - Line 7 (Stable tag)
3. `composer.json` - Add version field if not present

## Migration Notes for Users

### Breaking Change Warning
```
IMPORTANT: If you use wildcard CORS patterns like *.example.com, you must update your configuration.

Before 1.1.0: *.example.com matched both example.com and sub.example.com
After 1.1.0:  *.example.com matches ONLY sub.example.com (not the base domain)

Action Required: Add both patterns if you need both:
- https://example.com
- https://*.example.com
```

### HMAC Secret Rotation Reminder
Encourage users to rotate secrets after upgrade:
```bash
# Generate new secret
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Add to wp-config.php with new key ID
define('PCC_HMAC_KEY_2026_02', 'new-secret-here');
```

## Implementation Priority

### Phase 1 (Critical Security - Do First)
1. ✅ Create Signature_Validator service
2. Update REST controller to use Signature_Validator
3. Enhance CORS validation with wildcards
4. Add rate limiting for OPTIONS

### Phase 2 (Features & Config)
5. Add database version configuration
6. Update settings page with warnings and descriptions

### Phase 3 (Documentation)
7. Create README.md
8. Enhance SECURITY.md
9. Update readme.txt
10. Create CHANGELOG.md

### Phase 4 (Testing & Release)
11. Update tests
12. Update version numbers
13. Git commit and tag 1.1.0
14. Test on WordPress 6.7
15. Submit to WordPress.org

## Estimated Effort

- Phase 1: 2-3 hours
- Phase 2: 1-2 hours
- Phase 3: 3-4 hours (documentation is time-consuming)
- Phase 4: 1 hour
- **Total**: 7-10 hours for complete parity

## Files Summary

### New Files (4)
1. `includes/services/class-signature-validator.php` ✅
2. `README.md`
3. `CHANGELOG.md`
4. `tests/test-signature-validator.php`

### Modified Files (7)
1. `includes/rest/class-snapshot-rest-controller.php` - Use Signature_Validator
2. `includes/services/class-cors-manager.php` - Wildcard matching
3. `includes/services/class-snapshot-builder.php` - DB version config
4. `includes/admin/class-settings-page.php` - New fields, warnings
5. `includes/class-plugin.php` - Wire up new service
6. `SECURITY.md` - Comprehensive expansion
7. `readme.txt` - Version and changelog

### Version Files (3)
1. `project-context-connector.php` - Version constant
2. `readme.txt` - Stable tag
3. `composer.json` - Package version

## Next Steps

Choose implementation approach:

**Option A - Manual**: Follow this plan and implement changes one section at a time
**Option B - Assisted**: Request specific file implementations from Claude Code
**Option C - Batch**: Implement Phase 1 (critical security) immediately, then phases 2-4 as time allows

Would you like me to proceed with Phase 1 implementation now?
