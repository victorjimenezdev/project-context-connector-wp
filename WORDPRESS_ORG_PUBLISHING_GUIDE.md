# WordPress.org Plugin Publishing Guide

Complete guide to publish Project Context Connector to WordPress.org plugin directory.

## Prerequisites

- [x] WordPress.org account (you have this)
- [x] Plugin ready with proper readme.txt
- [x] Plugin tested and working
- [ ] SVN client installed

## Step 1: Install SVN (if not already installed)

Check if you have SVN:
```bash
svn --version
```

If not installed:
```bash
# macOS
brew install svn

# Or use Xcode command line tools (already includes SVN)
xcode-select --install
```

## Step 2: Submit Plugin to WordPress.org

### A. Go to Plugin Submission Page

1. Visit: https://wordpress.org/plugins/developers/add/
2. Log in with your WordPress.org account
3. You'll see a form to submit your plugin

### B. Upload Plugin ZIP

Create a clean ZIP file (without development files):

```bash
cd /Users/vj/Documents/work/personal/project-context-connector-wp

# Create a temporary directory for clean copy
mkdir -p /tmp/pcc-release
rsync -av --exclude='.git' \
  --exclude='.github' \
  --exclude='.DS_Store' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpcs.xml' \
  --exclude='phpstan.neon' \
  --exclude='phpunit.xml.dist' \
  --exclude='tests' \
  --exclude='IMPLEMENTATION_STATUS.md' \
  --exclude='UPGRADE_PLAN_1.1.0.md' \
  --exclude='WORDPRESS_ORG_PUBLISHING_GUIDE.md' \
  ./ /tmp/pcc-release/project-context-connector/

# Create ZIP
cd /tmp/pcc-release
zip -r project-context-connector.zip project-context-connector/

echo "ZIP file created at: /tmp/pcc-release/project-context-connector.zip"
```

### C. Fill Out Submission Form

On https://wordpress.org/plugins/developers/add/:

1. **Upload ZIP**: Upload the zip file you just created
2. **Plugin Name**: Project Context Connector (auto-detected from readme.txt)
3. **Description**: Auto-filled from readme.txt
4. **Agree to guidelines**: Check the box confirming you've read the guidelines

### D. Submit and Wait

Click **Submit Plugin** button.

**What happens next:**
- WordPress.org plugin review team will review your submission
- You'll receive an email within 1-14 days (usually 3-5 days)
- They'll either:
  - Approve your plugin and send SVN repository URL
  - Request changes/clarifications

## Step 3: After Approval - Set Up SVN Repository

Once approved, you'll receive an email with your SVN repository URL like:
```
https://plugins.svn.wordpress.org/project-context-connector/
```

### A. Check Out SVN Repository

```bash
cd /Users/vj/Documents/work/personal

# Check out the SVN repository
svn co https://plugins.svn.wordpress.org/project-context-connector/

# This creates a 'project-context-connector' directory with subdirectories:
# - trunk/     (development version)
# - tags/      (released versions)
# - assets/    (screenshots, banner, icon)
# - branches/  (optional, for experimental features)
```

### B. Understand SVN Structure

```
project-context-connector/
├── trunk/           # Your latest development code goes here
├── tags/            # Released versions (1.0.0, 1.1.0, etc.)
│   └── 1.1.0/      # Exact copy of trunk when you release
├── assets/          # Plugin directory assets (not included in download)
│   ├── banner-772x250.png    # Plugin page header (optional)
│   ├── banner-1544x500.png   # Retina banner (optional)
│   ├── icon-128x128.png      # Plugin icon (optional)
│   └── screenshot-1.png      # Screenshots referenced in readme.txt
└── branches/        # For development branches (rarely used)
```

## Step 4: Upload Your Plugin to SVN

### A. Copy Files to Trunk

```bash
cd /Users/vj/Documents/work/personal

# Copy your plugin files to SVN trunk (excluding dev files)
rsync -av --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.DS_Store' \
  --exclude='node_modules' \
  --exclude='vendor' \
  --exclude='composer.json' \
  --exclude='composer.lock' \
  --exclude='phpcs.xml' \
  --exclude='phpstan.neon' \
  --exclude='phpunit.xml.dist' \
  --exclude='tests' \
  --exclude='IMPLEMENTATION_STATUS.md' \
  --exclude='UPGRADE_PLAN_1.1.0.md' \
  --exclude='WORDPRESS_ORG_PUBLISHING_GUIDE.md' \
  /Users/vj/Documents/work/personal/project-context-connector-wp/ \
  /Users/vj/Documents/work/personal/project-context-connector/trunk/
```

### B. Add Plugin Assets (Optional but Recommended)

If you have plugin assets (banner, icon, screenshots):

```bash
cd /Users/vj/Documents/work/personal/project-context-connector/assets

# Add your assets
# - banner-772x250.png or banner-1544x500.png (plugin page banner)
# - icon-128x128.png or icon-256x256.png (plugin icon)
# - screenshot-1.png, screenshot-2.png, etc. (screenshots)

# Copy from your plugin's assets folder if you have them
cp /Users/vj/Documents/work/personal/project-context-connector-wp/assets/*.png ./
```

### C. Add Files to SVN

```bash
cd /Users/vj/Documents/work/personal/project-context-connector

# Add all new files to SVN
svn add trunk/* --force
svn add assets/* --force 2>/dev/null || true

# Check status
svn status

# You should see 'A' (Added) before new files
```

### D. Commit to Trunk

```bash
# Commit with a message
svn ci -m "Initial commit: Version 1.1.0

Security Features:
- Centralized Signature_Validator service
- Enhanced timestamp validation
- CORS wildcard subdomain matching
- OPTIONS request rate limiting
- Configurable database version exposure

Breaking Changes:
- CORS wildcard patterns now match ONLY subdomains

Documentation:
- Comprehensive README.md
- Enhanced SECURITY.md
- Full CHANGELOG.md"

# Enter your WordPress.org username and password when prompted
```

## Step 5: Create a Tagged Release

Once trunk is committed, create a tag for version 1.1.0:

```bash
cd /Users/vj/Documents/work/personal/project-context-connector

# Copy trunk to tags/1.1.0
svn cp trunk tags/1.1.0

# Commit the tag
svn ci -m "Tagging version 1.1.0" tags/1.1.0

# The plugin will now show version 1.1.0 in the WordPress.org directory
```

## Step 6: Verify Your Plugin

1. Visit: https://wordpress.org/plugins/project-context-connector/
2. Check that it shows version 1.1.0
3. Check the description, screenshots, and changelog
4. Download and test the plugin from WordPress.org

## Future Updates

### Releasing Version 1.2.0 (Example)

```bash
cd /Users/vj/Documents/work/personal/project-context-connector

# 1. Update trunk with new code
rsync -av --delete \
  --exclude='.git' \
  --exclude='.github' \
  --exclude='.DS_Store' \
  /Users/vj/Documents/work/personal/project-context-connector-wp/ \
  trunk/

# 2. Commit changes to trunk
svn ci -m "Update to version 1.2.0: [describe changes]"

# 3. Create new tag
svn cp trunk tags/1.2.0
svn ci -m "Tagging version 1.2.0" tags/1.2.0
```

## Important Notes

### readme.txt is King
- The `readme.txt` file controls what appears on WordPress.org
- **Stable tag** in readme.txt determines which version users download
- Always ensure readme.txt is up to date before tagging

### Assets Folder
- Files in `assets/` are NOT included in the plugin download
- They only appear on the WordPress.org plugin page
- Screenshots should be PNG or JPG format
- Banner: 772x250px (or 1544x500px for retina)
- Icon: 128x128px (or 256x256px for retina)

### SVN vs Git
- WordPress.org uses SVN, not Git
- Keep your Git repository separate (GitHub, Bitbucket, etc.)
- When releasing, copy from Git to SVN

### Common SVN Commands

```bash
# Check status
svn status

# Update from remote
svn update

# Add new files
svn add filename

# Delete files
svn delete filename

# Commit changes
svn ci -m "Commit message"

# View log
svn log

# Revert changes
svn revert filename
```

## Troubleshooting

### "Unable to connect to SVN repository"
- Check your internet connection
- Verify your WordPress.org credentials
- Try: `svn update --username your-wporg-username`

### "File already exists"
- Use `svn update` to sync with remote
- Resolve conflicts if any
- Then try committing again

### "readme.txt not found"
- Ensure readme.txt is in the trunk/ directory
- Check filename is exactly `readme.txt` (lowercase)
- Commit trunk before creating tags

### Plugin shows old version
- Check `Stable tag:` in readme.txt matches your tag version
- Ensure you committed the tag, not just created it
- WordPress.org caches aggressively - wait 15-30 minutes

## Getting Help

- **WordPress.org Plugin Handbook**: https://developer.wordpress.org/plugins/
- **SVN Guide**: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- **Plugin Guidelines**: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- **Support Forums**: https://wordpress.org/support/forum/plugins/

## Pre-Submission Checklist

Before submitting, verify:

- [ ] Plugin tested on WordPress 6.1+ and 6.7
- [ ] Plugin tested on PHP 8.0+
- [ ] No PHP errors or warnings
- [ ] readme.txt is properly formatted
- [ ] Screenshots are included (if any)
- [ ] License is GPL-compatible (GPL-2.0-or-later)
- [ ] No external dependencies (all bundled)
- [ ] No telemetry or phone-home
- [ ] Security best practices followed
- [ ] Translatable strings use text domain
- [ ] Proper escaping and sanitization

## Post-Publication Tasks

After your plugin is live:

1. **Announce**: Share on social media, your blog, etc.
2. **Monitor**: Watch support forums for questions
3. **Update**: Keep plugin updated for new WordPress versions
4. **Security**: Monitor for security issues and patch quickly
5. **Engage**: Respond to reviews and support requests

## Contact

If you have issues:
- WordPress.org plugins email: plugins@wordpress.org
- Plugin Review Team: https://make.wordpress.org/plugins/

---

**Ready to Submit?**

1. Create clean ZIP (Step 2B)
2. Go to https://wordpress.org/plugins/developers/add/
3. Upload and submit
4. Wait for approval email
5. Follow Steps 3-6 after approval

Good luck!
