<?php
/**
 * CORS header management for our namespace.
 *
 * @package SiteContextsnap
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
		if ( $origin && $this->matches_allowed_origin( $origin, $allowed_origins ) ) {
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
	 * Check if origin matches any allowed pattern.
	 *
	 * Supports exact matches and wildcard subdomain patterns like "https://*.example.com".
	 * Wildcard patterns match ONLY subdomains, not the base domain itself.
	 *
	 * @param string $origin          Origin to check.
	 * @param array  $allowed_origins Array of allowed origin patterns.
	 * @return bool True if origin is allowed.
	 */
	private function matches_allowed_origin( $origin, $allowed_origins ) {
		foreach ( $allowed_origins as $pattern ) {
			if ( $this->matches_origin( $origin, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if origin matches a pattern (exact or wildcard).
	 *
	 * @param string $origin  Origin (e.g., "https://sub.example.com").
	 * @param string $pattern Pattern (e.g., "https://example.com" or "https://*.example.com").
	 * @return bool True if matches.
	 */
	private function matches_origin( $origin, $pattern ) {
		$normalized_origin  = rtrim( $origin, '/' );
		$normalized_pattern = rtrim( $pattern, '/' );

		// Exact match including scheme and host.
		if ( 0 === strcasecmp( $normalized_origin, $normalized_pattern ) ) {
			return true;
		}

		// Wildcard subdomain: "*.example.com" or "https://*.example.com".
		if ( 0 === strpos( $normalized_pattern, '*.' ) ||
		     0 === strpos( $normalized_pattern, 'https://*.' ) ||
		     0 === strpos( $normalized_pattern, 'http://*.' ) ) {

			// Extract scheme and host from origin.
			$origin_scheme = wp_parse_url( $normalized_origin, PHP_URL_SCHEME );
			$origin_host   = wp_parse_url( $normalized_origin, PHP_URL_HOST );

			// Extract scheme and host from pattern.
			$pattern_scheme = wp_parse_url( $normalized_pattern, PHP_URL_SCHEME );
			if ( null === $pattern_scheme && 0 === strpos( $normalized_pattern, '*.' ) ) {
				$pattern_scheme = 'https'; // Default to https.
			}

			$pattern_host = preg_replace( '/^\w+:\/\//', '', $normalized_pattern );
			$pattern_host = ltrim( $pattern_host, '*.' );

			// Ensure schemes match (security: don't allow http pattern to match https origin).
			if ( $origin_scheme !== $pattern_scheme ) {
				return false;
			}

			// Wildcard should match ONLY subdomains, not base domain.
			// For "*.example.com" to match "example.com", add both patterns explicitly.
			return $origin_host !== '' &&
			       $origin_host !== $pattern_host &&
			       0 === substr_compare( strtolower( $origin_host ), '.' . strtolower( $pattern_host ), - strlen( '.' . $pattern_host ) );
		}

		return false;
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
