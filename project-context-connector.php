<?php
/**
 * Plugin Name:       Project Context Connector
 * Description:       Expose a sanitized, read-only project snapshot via REST and WP-CLI for Slack/Teams prompts, scripts, and CI.
 * Version:           1.1.0
 * Requires at least: 6.1
 * Requires PHP:      8.0
 * Tested up to:      6.9
 * Stable tag:        1.1.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       project-context-connector
 * Domain Path:       /languages
 *
 * @package           ProjectContextConnector
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PCC_VERSION', '1.1.0' );
define( 'PCC_PLUGIN_FILE', __FILE__ );
define( 'PCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Simple PSR-4 style autoloader for the PCC\ namespace.
 */
require_once PCC_PLUGIN_DIR . 'includes/class-autoloader.php';

use PCC\Plugin;

// Bootstrap the plugin.
add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance()->init();
	}
);

/**
 * Map the custom capability pcc_read_snapshot to a real primitive cap (filterable).
 *
 * @param string[] $caps Array of the user's capabilities.
 * @param string   $cap  Capability being checked.
 * @return string[]
 */
add_filter(
	'map_meta_cap',
	static function ( $caps, $cap ) {
		if ( 'pcc_read_snapshot' === $cap ) {
			$required = apply_filters( 'pcc_required_capability', 'manage_options' );
			return array( $required );
		}
		return $caps;
	},
	10,
	2
);

/**
 * Privacy policy suggestion (no tracking by default).
 */
add_action(
	'admin_init',
	static function () {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			$policy_text  = '<p>';
			$policy_text .= esc_html__( 'This plugin exposes a read-only technical snapshot of the site via REST API and WP-CLI. It does not collect personal data or send information to remote services. If you enable optional features like CORS allow-lists or HMAC keys, note that no secrets are stored in the database.', 'project-context-connector' );
			$policy_text .= '</p>';
			wp_add_privacy_policy_content(
				__( 'Project Context Connector', 'project-context-connector' ),
				wp_kses_post( $policy_text )
			);
		}
	}
);
