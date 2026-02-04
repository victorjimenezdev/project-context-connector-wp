# Contributing

## Requirements
- PHP 7.4+ (tested on 7.4–8.3), Node 18+, Composer
- WordPress 6.1+

## Setup
```bash
composer install
Code quality
bash
Copy
composer lint         # PHPCS (WPCS)
composer phpstan      # Static analysis
composer test         # PHPUnit
Tests
The test suite uses the WordPress test framework. Ensure WP_PHPUNIT__DIR is set or use the helper script to download the test lib.

Internationalization
Generate POT:

bash
Copy
wp i18n make-pot . languages/project-context-connector.pot
Releasing
Update version in the main plugin file and readme.txt stable tag.

Tag release and push to /tags/x.y.z in the WordPress.org SVN.