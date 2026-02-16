<?php
/**
 * Per-origin/IP rate limiter (sliding window approximation).
 *
 * @package ProjectContextConnector
 */

namespace PCC\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simple counter-based limiter using transients.
 */
class Rate_Limiter {

	/**
	 * Check if the given key is within the rate limit.
	 *
	 * @param string $key Key per origin/IP (hashed).
	 * @return array { allowed: bool, retry_after: int }
	 */
	public function check( $key ) {
		$options   = get_option( 'pcc_options', array() );
		$threshold = isset( $options['rate_limit_threshold'] ) ? (int) $options['rate_limit_threshold'] : 60;
		$window    = isset( $options['rate_limit_window'] ) ? (int) $options['rate_limit_window'] : 60;

		if ( $threshold <= 0 || $window <= 0 ) {
			return array(
				'allowed'     => true,
				'retry_after' => 0,
			);
		}

		$tkey   = 'pcc_rl_' . md5( (string) $key );
		$record = get_transient( $tkey );

		$now = time();

		if ( ! is_array( $record ) || ! isset( $record['count'], $record['start'] ) ) {
			$record = array(
				'count' => 1,
				'start' => $now,
			);
			set_transient( $tkey, $record, $window );
			return array(
				'allowed'     => true,
				'retry_after' => 0,
			);
		}

		// Sliding window approximation: decay if part of the window elapsed.
		$elapsed       = max( 0, $now - (int) $record['start'] );
		$window_factor = ( $elapsed >= $window ) ? 1 : ( $elapsed / $window );

		if ( $elapsed >= $window ) {
			$record = array(
				'count' => 1,
				'start' => $now,
			);
			set_transient( $tkey, $record, $window );
			return array(
				'allowed'     => true,
				'retry_after' => 0,
			);
		}

		$record['count']++;
		set_transient( $tkey, $record, $window - $elapsed );

		if ( $record['count'] > $threshold ) {
			return array(
				'allowed'     => false,
				'retry_after' => max( 1, $window - $elapsed ),
			);
		}

		return array(
			'allowed'     => true,
			'retry_after' => 0,
		);
	}

	/**
	 * Build a limiter key from REST request context.
	 *
	 * @param \WP_REST_Request $request REST request.
	 * @return string
	 */
	public function key_from_request( \WP_REST_Request $request ) {
		$origin = (string) $request->get_header( 'origin' );

		if ( function_exists( 'rest_get_ip_address' ) ) {
			$ip = (string) rest_get_ip_address();
		} else {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		}

		$user_id  = get_current_user_id();
		$identity = $origin . '|' . $ip . '|' . ( $user_id ? (string) $user_id : '-' );
		return 'pcc:' . md5( $identity );
	}
}
