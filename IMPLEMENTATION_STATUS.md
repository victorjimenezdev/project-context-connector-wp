# WordPress Plugin 1.1.0 - Implementation Status

## ✅ COMPLETED (Critical Security - Phase 1)

### 1. Signature Validator Service ✅
**File**: `includes/services/class-signature-validator.php`
- Created centralized HMAC signature validation
- Enhanced timestamp validation (leading zeros, sanity checks 2000-2100)
- Proper constant lookup for secrets

### 2. REST Controller Refactoring ✅
**File**: `includes/rest/class-snapshot-rest-controller.php`
- Integrated Signature_Validator service
- Removed duplicate HMAC logic (30+ lines)
- Cleaner, more maintainable code

### 3. Plugin Service Wiring ✅
**File**: `includes/class-plugin.php`
- Added Signature_Validator to service container
- Wired into REST controller constructor

### 4. CORS Wildcard Validation ✅
**File**: `includes/services/class-cors-manager.php`
- Added `matches_origin()` and `matches_allowed_origin()` methods
- Wildcard subdomain support with scheme enforcement
- **BREAKING**: `*.example.com` now matches ONLY subdomains, not base domain
- Prevents cross-scheme attacks (http vs https)

### 5. OPTIONS Rate Limiting ✅
**Status**: Already implemented
- OPTIONS requests go through REST controller's `maybe_throttle_request()`
- No additional changes needed

## 🔄 REMAINING (Quick Implementation)

### Phase 2: Configuration & Features

#### 1. Database Version Configuration
**Files to modify**:
- `includes/admin/views/settings-page.php` - Add checkbox field
- `includes/services/class-snapshot-builder.php` - Make DB version conditional

**Code snippet for settings-page.php**:
```php
<tr>
    <th scope="row">
        <label for="pcc_expose_database_version">
            <?php esc_html_e( 'Expose Database Version', 'project-context-connector' ); ?>
        </label>
    </th>
    <td>
        <input
            type="checkbox"
            name="pcc_options[expose_database_version]"
            id="pcc_expose_database_version"
            value="1"
            <?php checked( ! empty( $options['expose_database_version'] ) ); ?>
        />
        <p class="description">
            <?php esc_html_e( 'Include database driver and version. Disable for minimal information disclosure.', 'project-context-connector' ); ?>
        </p>
    </td>
</tr>
```

**Code snippet for class-snapshot-builder.php** (around line where DB info is built):
```php
$options = get_option( 'pcc_options', array() );
$expose_db_version = ! empty( $options['expose_database_version'] );

$data['environment']['database'] = array(
    'server'  => $expose_db_version ? $wpdb->db_server_info() : null,
    'version' => $expose_db_version ? $wpdb->db_version() : null,
);
```

#### 2. HTTP Origin Warning in Settings
**File**: `includes/admin/views/settings-page.php`

Add JavaScript validation to warn about HTTP origins:
```php
<script>
jQuery(document).ready(function($) {
    $('#pcc_allowed_origins').on('blur', function() {
        var origins = $(this).val();
        if (origins.toLowerCase().indexOf('http://') !== -1) {
            if (!$('.http-warning').length) {
                $(this).after('<p class="http-warning" style="color: #d63638;"><strong>Warning:</strong> HTTP origins are insecure. Use HTTPS in production.</p>');
            }
        } else {
            $('.http-warning').remove();
        }
    });
});
</script>
```

### Phase 3: Documentation

#### 1. README.md (Priority: HIGH)
Create `/README.md` based on Drupal version with WordPress-specific changes:
- Change authentication examples to WordPress Application Passwords
- Update endpoints to `/wp-json/pcc/v1/snapshot`
- Update Drush commands to `wp pcc snapshot`
- Update configuration path to Settings → Project Context Connector
- Update secret format to `define('PCC_HMAC_KEY_keyid', 'secret');`

#### 2. SECURITY.md Enhancement (Priority: HIGH)
Expand current 8-line file to match Drupal version:
- Threat model section
- Secret management (generation, storage, rotation)
- Compromised secret response procedure
- CORS security configuration
- Compliance considerations

#### 3. CHANGELOG.md (Priority: HIGH)
Create `/CHANGELOG.md`:
```markdown
# Changelog

## [1.1.0] - 2026-02-16

### Added
- Signature_Validator service with enhanced timestamp validation
- CORS wildcard subdomain support with scheme enforcement
- Comprehensive security improvements

### Changed
- **BREAKING**: Wildcard CORS patterns (*.example.com) now match ONLY subdomains
- Refactored HMAC validation into dedicated service

### Security
- Enhanced timestamp validation prevents edge case bypasses
- Scheme enforcement in CORS prevents cross-scheme attacks
- OPTIONS requests properly rate-limited

## [1.0.0] - 2025-08-23
Initial release
```

#### 4. readme.txt Update (Priority: HIGH)
**Changes**:
- Line 7: `Stable tag: 1.1.0`
- Line 5: `Tested up to: 6.7`
- Add changelog section for 1.1.0
- Add FAQ about wildcard CORS change

### Phase 4: Version Update & Release

#### Files to update:
1. `project-context-connector.php`:
   - Line 5: `Version: 1.1.0`
   - Line 22: `define( 'PCC_VERSION', '1.1.0' );`

2. `readme.txt`:
   - Line 7: `Stable tag: 1.1.0`

3. `composer.json` (if has version field)

## Git Commit Plan

```bash
cd /Users/vj/Documents/work/personal/project-context-connector-wp

git add .
git commit -m "Release 1.1.0: Security improvements and parity with Drupal module

Security Enhancements:
- Add Signature_Validator service with enhanced timestamp validation
- Improve CORS wildcard validation with scheme enforcement
- Refactor HMAC logic into centralized service
- Wildcard patterns now match ONLY subdomains (breaking change)

Code Quality:
- Remove duplicate HMAC validation code
- Better service separation and testability

Breaking Changes:
- CORS wildcard patterns (*.example.com) now match ONLY subdomains
  To match both base and subdomains, add both patterns explicitly

Files Changed:
- New: includes/services/class-signature-validator.php
- Modified: includes/class-plugin.php (wire new service)
- Modified: includes/rest/class-snapshot-rest-controller.php (use validator)
- Modified: includes/services/class-cors-manager.php (wildcard support)

Documentation and configuration enhancements to follow in subsequent commits."

git tag -a 1.1.0 -m "Version 1.1.0 - Security improvements"
```

## Testing Checklist

Before pushing:
- [ ] Test basic auth endpoint works
- [ ] Test HMAC signed endpoint works
- [ ] Test CORS exact match still works
- [ ] Test CORS wildcard `*.example.com` matches `sub.example.com` but NOT `example.com`
- [ ] Test rate limiting returns 429
- [ ] Test WP-CLI command works: `wp pcc snapshot`

## Estimated Remaining Time

- Database version config: 15 minutes
- README.md: 60 minutes
- SECURITY.md: 30 minutes
- CHANGELOG.md: 10 minutes
- readme.txt update: 10 minutes
- Version updates: 5 minutes
- Testing: 20 minutes

**Total**: ~2.5 hours to complete full 1.1.0 release

## Summary

**Completed**: Critical security improvements (Phase 1) - Module is now significantly more secure
**Remaining**: Configuration polish + comprehensive documentation (Phases 2-4)

The WordPress plugin now has the same security improvements as the Drupal 1.1.0 release!
