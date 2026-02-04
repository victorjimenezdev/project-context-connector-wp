<?php
/**
 * REST tests for PCC.
 */

class PCC_REST_Snapshot_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		wp_set_current_user( 0 );
	}

	protected function tearDown(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_pcc_rl_%' OR option_name LIKE '_transient_timeout_pcc_rl_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
		delete_transient( 'pcc_snapshot_cache' );
		parent::tearDown();
	}

	public function test_snapshot_requires_auth() {
		$request  = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_snapshot_with_capability() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$request  = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'environment', $data );
		$this->assertArrayHasKey( 'plugins', $data );
	}

	public function test_network_active_plugins_are_reported() {
		$plugin_dir  = WP_PLUGIN_DIR . '/pcc-mu-test';
		$plugin_file = $plugin_dir . '/pcc-mu-test.php';
		wp_mkdir_p( $plugin_dir );
		file_put_contents(
			$plugin_file,
			"<?php\n/*\nPlugin Name: PCC MU Test\nVersion: 1.0.0\n*/"
		);
		wp_clean_plugins_cache( true );
		update_site_option( 'active_sitewide_plugins', array( 'pcc-mu-test/pcc-mu-test.php' => time() ) );
		update_option( 'active_plugins', array() );

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request  = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data  = $response->get_data();
		$found = false;
		foreach ( $data['plugins']['active'] as $plugin ) {
			if ( isset( $plugin['plugin_file'] ) && 'pcc-mu-test/pcc-mu-test.php' === $plugin['plugin_file'] ) {
				$found = ! empty( $plugin['network_active'] );
				break;
			}
		}
		$this->assertTrue( $found, 'Expected network-active plugin entry.' );

		// Cleanup.
		if ( file_exists( $plugin_file ) ) {
			wp_delete_file( $plugin_file );
		}
		if ( is_dir( $plugin_dir ) ) {
			rmdir( $plugin_dir );
		}
		wp_clean_plugins_cache( true );
		delete_site_option( 'active_sitewide_plugins' );
	}

	public function test_rate_limit_returns_retry_after_header() {
		update_option(
			'pcc_options',
			array(
				'rate_limit_threshold' => 1,
				'rate_limit_window'    => 30,
				'cache_ttl'            => 0,
			)
		);
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$request->set_header( 'origin', 'https://example.com' );

		$this->assertSame( 200, rest_get_server()->dispatch( $request )->get_status() );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 429, $response->get_status() );
		$headers = $response->get_headers();
		$this->assertArrayHasKey( 'Retry-After', $headers );
		$this->assertSame( '30', $headers['Retry-After'] );
	}

	public function test_bearer_auth_can_be_disabled() {
		update_option(
			'pcc_options',
			array(
				'allow_bearer'         => false,
				'rate_limit_threshold' => 60,
				'rate_limit_window'    => 60,
			)
		);
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$request = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$request->set_header( 'authorization', 'Bearer fake-token' );

		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 401, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( 'pcc_bearer_disabled', $data['code'] );
	}
}
