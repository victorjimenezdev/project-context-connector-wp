<?php
/**
 * Cache tests (basic).
 */

class PCC_Cache_Test extends WP_UnitTestCase {

	public function test_snapshot_is_cached() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );
		$request  = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$first = $response->get_data();

		$response2 = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response2->get_status() );
		$second = $response2->get_data();

		$this->assertEquals( $first, $second );
	}
}
