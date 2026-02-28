<?php
/**
 * Uninstall hook to remove plugin options.
 *
 * @package SiteContextsnap
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete options on uninstall only.
delete_option( 'pcc_options' );

// Network-wise cleanup (if stored as site options in future).
if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	delete_site_option( 'pcc_options' );
}
