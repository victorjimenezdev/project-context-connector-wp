# How to Run Official WordPress Plugin Check

This guide shows you how to run the official WordPress Plugin Check tool on this plugin.

## Option 1: Using Local WordPress Installation (Recommended)

If you have a local WordPress installation (Lando, Local, MAMP, etc.):

### Step 1: Install Plugin Check

```bash
# Via WordPress admin
1. Go to: Plugins → Add New
2. Search for "Plugin Check"
3. Install and activate

# Or via WP-CLI (if available)
wp plugin install plugin-check --activate
```

### Step 2: Install Your Plugin

```bash
# Copy plugin to WordPress installation
cp -r /Users/vj/Documents/work/personal/project-context-connector-wp \
  /path/to/wordpress/wp-content/plugins/project-context-connector

# Or via WP-CLI
wp plugin install /Users/vj/Documents/work/personal/project-context-connector-wp \
  --activate
```

### Step 3: Run Plugin Check

**Via WordPress Admin:**
1. Go to: Tools → Plugin Check
2. Select "Project Context Connector"
3. Click "Check it!"
4. Review results

**Via WP-CLI:**
```bash
wp plugin check project-context-connector
```

## Option 2: Using Docker (Quick Setup)

Create a temporary WordPress environment with Docker:

### Step 1: Create docker-compose.yml

```bash
cd /Users/vj/Documents/work/personal/project-context-connector-wp

cat > docker-compose.yml << 'EOF'
version: '3.8'

services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - ./:/var/www/html/wp-content/plugins/project-context-connector

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: rootpassword
EOF
```

### Step 2: Start WordPress

```bash
docker-compose up -d

# Wait for WordPress to be ready (30 seconds)
sleep 30

# Complete WordPress installation
docker-compose exec wordpress wp core install \
  --url=http://localhost:8080 \
  --title="Plugin Check Test" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com \
  --skip-email \
  --allow-root
```

### Step 3: Install Plugin Check

```bash
# Install and activate Plugin Check
docker-compose exec wordpress wp plugin install plugin-check \
  --activate --allow-root

# Activate your plugin
docker-compose exec wordpress wp plugin activate project-context-connector \
  --allow-root
```

### Step 4: Run Plugin Check

```bash
# Run the check
docker-compose exec wordpress wp plugin check project-context-connector \
  --format=table --allow-root

# Or with detailed output
docker-compose exec wordpress wp plugin check project-context-connector \
  --fields=type,code,message --format=table --allow-root
```

### Step 5: Cleanup

```bash
# Stop and remove containers
docker-compose down -v

# Remove docker-compose.yml
rm docker-compose.yml
```

## Option 3: Online Checker (Limited)

WordPress.org doesn't provide an online checker, but you can:

1. **Submit to WordPress.org** (they'll run checks automatically)
2. **Use theme-check plugin** as a proxy (similar checks):
   ```bash
   wp plugin install theme-check --activate
   wp theme-check check-plugin project-context-connector
   ```

## Understanding Plugin Check Results

### Check Categories

1. **Errors** - Must be fixed before WordPress.org approval
   - Security issues
   - Required functions missing
   - Breaking changes

2. **Warnings** - Should be fixed (may block approval)
   - Deprecated functions
   - Missing recommended features
   - Performance issues

3. **Recommendations** - Best practices (won't block approval)
   - Code style improvements
   - Optional features

### Common Issues and Fixes

#### Direct File Access
```php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
```

#### Escaping Output
```php
echo esc_html( $text );
echo esc_attr( $attribute );
echo esc_url( $url );
```

#### Sanitizing Input
```php
$value = sanitize_text_field( $_POST['field'] );
$email = sanitize_email( $_POST['email'] );
$url = esc_url_raw( $_POST['url'] );
```

#### Text Domain
```php
__( 'Text', 'project-context-connector' );
esc_html__( 'Text', 'project-context-connector' );
```

## Expected Results for This Plugin

Based on the manual audit, this plugin should:

✅ **Pass all security checks**
- All files have ABSPATH check
- All output is escaped
- All input is sanitized
- No direct SQL queries
- Proper capability checks

✅ **Pass all code quality checks**
- Consistent text domain
- No deprecated functions
- Proper plugin headers
- GPL-compatible license

✅ **Pass all WordPress.org requirements**
- No external API calls
- No obfuscated code
- Proper readme.txt
- No trademark violations

## If Issues Are Found

1. **Review the error message** carefully
2. **Check file:line reference** to locate the issue
3. **Apply the recommended fix**
4. **Re-run Plugin Check** to verify
5. **Commit the fix** to git

## Resources

- **Plugin Check Plugin**: https://wordpress.org/plugins/plugin-check/
- **Plugin Handbook**: https://developer.wordpress.org/plugins/
- **Plugin Guidelines**: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- **Security Best Practices**: https://developer.wordpress.org/apis/security/

---

## Quick Docker Command (Copy-Paste)

```bash
# Complete setup and check (one command)
cd /Users/vj/Documents/work/personal/project-context-connector-wp && \
cat > docker-compose.yml << 'EOF'
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: wordpress
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - ./:/var/www/html/wp-content/plugins/project-context-connector
  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: wordpress
      MYSQL_ROOT_PASSWORD: rootpassword
EOF
docker-compose up -d && \
sleep 30 && \
docker-compose exec wordpress wp core install \
  --url=http://localhost:8080 \
  --title="Test" \
  --admin_user=admin \
  --admin_password=admin \
  --admin_email=admin@example.com \
  --skip-email \
  --allow-root && \
docker-compose exec wordpress wp plugin install plugin-check \
  --activate --allow-root && \
docker-compose exec wordpress wp plugin activate project-context-connector \
  --allow-root && \
docker-compose exec wordpress wp plugin check project-context-connector \
  --format=table --allow-root
```

Then cleanup:
```bash
docker-compose down -v && rm docker-compose.yml
```
