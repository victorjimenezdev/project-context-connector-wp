<?php
/**
 * HMAC Signature Validator.
 *
 * @package SiteContextsnap
 */

namespace PCC\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates HMAC signed requests for the signed snapshot route.
 *
 * Signature scheme:
 *   base = "<METHOD>\n<PATH>\n<TIMESTAMP>"
 *   signature = hex( HMAC-SHA256( base, secret ) )
 *
 * Required headers:
 *   X-PCC-Key, X-PCC-Timestamp, X-PCC-Signature
 *
 * Secrets are stored in wp-config.php:
 *   define('PCC_HMAC_KEYS_JSON', '{"key-id":"strong-random-secret"}');
 *   or
 *   define('PCC_HMAC_KEY_keyid', 'strong-random-secret');
 */
class Signature_Validator {

	/**
	 * Validate request signature.
	 *
	 * @param string $key_id    Key ID from X-PCC-Key header.
	 * @param string $timestamp Timestamp from X-PCC-Timestamp header.
	 * @param string $signature Signature from X-PCC-Signature header.
	 * @param string $method    HTTP method (e.g., 'GET').
	 * @param string $path      Request path (e.g., '/wp-json/pcc/v1/snapshot/signed').
	 * @param int    $skew_seconds Allowed clock skew in seconds (default 300).
	 * @return bool True if signature is valid.
	 */
	public function is_valid( $key_id, $timestamp, $signature, $method, $path, $skew_seconds = 300 ) {
		// Validate inputs are non-empty.
		if ( '' === $key_id || '' === $timestamp || '' === $signature ) {
			return false;
		}

		// Load secret from wp-config.php.
		$secret = $this->lookup_hmac_secret( $key_id );
		if ( '' === $secret ) {
			return false;
		}

		// Timestamp must be unix seconds and within skew.
		// Validate format: must be all digits, no leading zeros (except "0" itself),
		// no negative numbers, and reasonable length (10-11 digits for unix time).
		if ( ! ctype_digit( $timestamp ) || strlen( $timestamp ) > 11 || strlen( $timestamp ) < 1 ) {
			return false;
		}
		// Reject leading zeros (except "0" itself).
		if ( strlen( $timestamp ) > 1 && '0' === $timestamp[0] ) {
			return false;
		}
		$ts = (int) $timestamp;
		// Sanity check: timestamp should be reasonable (after 2000, before 2100).
		if ( $ts < 946684800 || $ts > 4102444800 ) {
			return false;
		}

		// Check timestamp is within skew window.
		$now = time();
		if ( abs( $now - $ts ) > max( 1, $skew_seconds ) ) {
			return false;
		}

		// Canonical string: METHOD + PATH + TIMESTAMP.
		$base = strtoupper( $method ) . "\n" . $path . "\n" . $timestamp;
		$expected = hash_hmac( 'sha256', $base, $secret );

		// Timing-safe comparison.
		return hash_equals( $expected, strtolower( $signature ) );
	}

	/**
	 * Resolve HMAC secret for a key id from wp-config.php.
	 *
	 * Supported:
	 * - define('PCC_HMAC_KEYS_JSON', '{"key1":"secret1","key2":"secret2"}');
	 * - define('PCC_HMAC_KEYS', '{"key1":"secret1"}');
	 * - define('PCC_HMAC_KEY_mykey', 'secretvalue');
	 *
	 * @param string $key_id Key ID.
	 * @return string Secret or empty string if not found.
	 */
	private function lookup_hmac_secret( $key_id ) {
		// Priority 1: PCC_HMAC_KEYS_JSON.
		if ( defined( 'PCC_HMAC_KEYS_JSON' ) ) {
			$keys = json_decode( PCC_HMAC_KEYS_JSON, true );
			if ( is_array( $keys ) && isset( $keys[ $key_id ] ) ) {
				return (string) $keys[ $key_id ];
			}
		}

		// Priority 2: PCC_HMAC_KEYS.
		if ( defined( 'PCC_HMAC_KEYS' ) ) {
			$keys = json_decode( PCC_HMAC_KEYS, true );
			if ( is_array( $keys ) && isset( $keys[ $key_id ] ) ) {
				return (string) $keys[ $key_id ];
			}
		}

		// Priority 3: PCC_HMAC_KEY_{key_id} (sanitize key_id for constant name).
		$constant_name = 'PCC_HMAC_KEY_' . preg_replace( '/[^a-z0-9_]/i', '_', $key_id );
		if ( defined( $constant_name ) ) {
			return (string) constant( $constant_name );
		}

		return '';
	}
}
