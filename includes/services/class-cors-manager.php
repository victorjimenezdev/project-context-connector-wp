<?php
/**
 * CORS header management for our namespace.
 *
 * @package ProjectContextConnector
 */

namespace PCC\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends restrictive CORS headers based on allow-list.
 */
class CORS_Manager {

	/**
	 * Namespace to scope CORS.
	 *
	 * @var string
	 */
	const NAMESPACE = 'pcc/v1';

	/**
	 * Constructor: hook header emission.
	 */
	public function __construct() {
		add_filter( 'rest_pre_serve_request', array( $this, 'maybe_send_cors_headers' ), 10, 3 );
	}

	/**
	 * Send CORS headers for allowed origins when hitting our namespace.
	 *
	 * @param bool               $served  If the request has already been served.
	 * @param \WP_HTTP_Response  $result  Result to send.
	 * @param \WP_REST_Request   $request Request.
	 * @return bool
	 */
	public function maybe_send_cors_headers( $served, $result, $request ) {
		$route = (string) $request->get_route();
		if ( strpos( $route, '/' . self::NAMESPACE . '/' ) !== 0 ) {
			return $served;
		}

		$options      = get_option( 'pcc_options', array() );
		$cors_enabled = ! empty( $options['cors_enabled'] );
		if ( ! $cors_enabled ) {
			return $served;
		}

		$allowed_origins = array();
		if ( ! empty( $options['allowed_origins'] ) && is_array( $options['allowed_origins'] ) ) {
			$allowed_origins = array_map( 'strval', $options['allowed_origins'] );
		}

		$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $origin && in_array( $origin, $allowed_origins, true ) ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Vary: Origin' );
			header( 'Access-Control-Allow-Methods: GET, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Authorization, X-WP-Nonce, X-PCC-Key, X-PCC-Timestamp, X-PCC-Signature' );
			header( 'Access-Control-Max-Age: 600' );
		}

		// Handle preflight for OPTIONS.
		if ( 'OPTIONS' === $request->get_method() ) {
			header( 'Content-Type: application/json; charset=' . get_option( 'blog_charset' ) );
			echo wp_json_encode( array( 'success' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return true;
		}

		return $served;
	}

	/**
	 * Check IP allow-list gate.
	 *
	 * @param string $ip IP address.
	 * @return bool True if allowed or no list configured.
	 */
	public function is_ip_allowed( $ip ) {
		$options  = get_option( 'pcc_options', array() );
		$allow_ip = isset( $options['allow_ips'] ) && is_array( $options['allow_ips'] ) ? $options['allow_ips'] : array();
		if ( empty( $allow_ip ) ) {
			return true; // No restriction configured.
		}
		return in_array( $ip, $allow_ip, true );
	}
}
