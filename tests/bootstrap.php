<?php
/**
 * PHPUnit bootstrap for PCC.
 */

$_tests_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( ! $_tests_dir ) {
	// Try default path if wp scaffolded tests are installed.
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/bootstrap.php' ) ) {
	echo "Could not find WordPress tests bootstrap in {$_tests_dir}\n";
	exit( 1 );
}

require $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/site-contextsnap.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
