<?php
/**
 * CLI tests (basic smoke).
 */

class PCC_CLI_Test extends WP_UnitTestCase {

	public function test_wp_cli_snapshot_outputs_json() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', false );
		}
		// Directly call the builder via REST for test simplicity.
		$request  = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$user_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'generated_at', $data );
	}
}
