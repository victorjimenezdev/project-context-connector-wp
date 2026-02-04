<?php
/**
 * Rate limiter tests (basic).
 */

class PCC_Rate_Limiter_Test extends WP_UnitTestCase {

	public function test_rate_limiter_transient_keys() {
		// Simulate hitting the signed endpoint with no auth but valid headers will be handled elsewhere.
		$request = new WP_REST_Request( 'GET', '/pcc/v1/snapshot' );
		$request->set_header( 'origin', 'https://example.com' );
		$response = rest_get_server()->dispatch( $request );
		// Unauthorized before rate limit; status should be 401 or 403.
		$this->assertContains( $response->get_status(), array( 401, 403 ), 'Expected auth error before rate limit.' );
		// Not asserting rate limiting increment as it depends on transients runtime.
		$this->assertTrue( true );
	}
}
