<?php
/**
 * Snapshot cache manager (transients/object cache).
 *
 * @package ProjectContextConnector
 */

namespace PCC\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages snapshot caching and invalidation.
 */
class Cache_Manager {

	/**
	 * Transient key.
	 *
	 * @var string
	 */
	const KEY = 'pcc_snapshot_cache';

	/**
	 * Get cache TTL from options (default 300 seconds).
	 *
	 * @return int
	 */
	public function ttl() {
		$options = get_option( 'pcc_options', array() );
		$ttl     = isset( $options['cache_ttl'] ) ? (int) $options['cache_ttl'] : 300;
		return max( 0, $ttl );
	}

	/**
	 * Fetch snapshot array from cache.
	 *
	 * @return array|null
	 */
	public function get() {
		$cached = get_transient( self::KEY );
		return is_array( $cached ) ? $cached : null;
	}

	/**
	 * Store snapshot in cache.
	 *
	 * @param array $data Snapshot data.
	 * @return void
	 */
	public function set( array $data ) {
		$ttl = $this->ttl();
		if ( $ttl > 0 ) {
			set_transient( self::KEY, $data, $ttl );
		}
	}

	/**
	 * Purge snapshot cache.
	 *
	 * @return void
	 */
	public function purge() {
		delete_transient( self::KEY );
	}
}
