<?php
/**
 * Autoloader for PCC\ classes.
 *
 * @package ProjectContextConnector
 */

namespace PCC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register autoloader.
 *
 * @return void
 */
spl_autoload_register(
	static function ( $class ) {
		if ( 0 !== strpos( $class, __NAMESPACE__ . '\\' ) ) {
			return;
		}
		$relative = substr( $class, strlen( __NAMESPACE__ . '\\' ) );
		$relative = str_replace( '\\', '/', $relative );
		$parts    = array_map(
			static function ( $segment ) {
				return strtolower( str_replace( '_', '-', $segment ) );
			},
			explode( '/', $relative )
		);

		$filename = array_pop( $parts );
		$dir      = $parts ? implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR : '';

		$candidate = PCC_PLUGIN_DIR . 'includes/' . $dir . 'class-' . $filename . '.php';
		if ( file_exists( $candidate ) ) {
			require_once $candidate;
			return;
		}

		$fallback = PCC_PLUGIN_DIR . 'includes/' . $dir . $filename . '.php';
		if ( file_exists( $fallback ) ) {
			require_once $fallback;
		}
	}
);
