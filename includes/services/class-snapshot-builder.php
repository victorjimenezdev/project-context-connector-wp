<?php
/**
 * Build the high-signal, stable snapshot.
 *
 * @package SiteContextsnap
 */

namespace PCC\Services;

use WP_Theme;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snapshot builder service.
 */
class Snapshot_Builder {

	/**
	 * Update metadata service.
	 *
	 * @var Update_Metadata
	 */
	private $updates;

	/**
	 * Cache manager.
	 *
	 * @var Cache_Manager
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param Update_Metadata $updates Update metadata service.
	 * @param Cache_Manager   $cache   Cache manager.
	 */
	public function __construct( Update_Metadata $updates, Cache_Manager $cache ) {
		$this->updates = $updates;
		$this->cache   = $cache;
	}

	/**
	 * Build (or return cached) snapshot array.
	 *
	 * @return array
	 */
	public function snapshot() {
		$cached = $this->cache->get();
		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$env_type = function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : 'production';
		$locale   = get_locale();

		$core_version = get_bloginfo( 'version' );

		$options           = get_option( 'pcc_options', array() );
		$expose_db_version = ! empty( $options['expose_database_version'] );

		$db_server  = '';
		$db_version = '';
		if ( $expose_db_version ) {
			$db_server  = isset( $wpdb->db_server_info ) ? (string) $wpdb->db_server_info : '';
			$db_version = method_exists( $wpdb, 'db_version' ) ? (string) $wpdb->db_version() : '';
		}

		$theme     = wp_get_theme();
		$parent    = $theme->parent();
		$theme_arr = array(
			'name'       => (string) $theme->get( 'Name' ),
			'version'    => (string) $theme->get( 'Version' ),
			'stylesheet' => (string) $theme->get_stylesheet(),
			'template'   => (string) $theme->get_template(),
			'is_child'   => $parent instanceof WP_Theme,
		);
		if ( $parent instanceof WP_Theme ) {
			$theme_arr['parent'] = array(
				'name'       => (string) $parent->get( 'Name' ),
				'version'    => (string) $parent->get( 'Version' ),
				'stylesheet' => (string) $parent->get_stylesheet(),
				'template'   => (string) $parent->get_template(),
			);
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$all_plugins         = get_plugins();
		$active_plugins      = (array) get_option( 'active_plugins', array() );
		$active_sitewide_map = (array) get_site_option( 'active_sitewide_plugins', array() );
		$all_active_files    = array_unique( array_merge( $active_plugins, array_keys( $active_sitewide_map ) ) );

		$plugin_updates = $this->updates->plugin_updates();
		$plugins        = array();

		foreach ( $all_active_files as $file ) {
			if ( ! isset( $all_plugins[ $file ] ) ) {
				continue;
			}
			$data           = $all_plugins[ $file ];
			$network_active = isset( $active_sitewide_map[ $file ] );
			$update         = isset( $plugin_updates[ $file ] ) ? $plugin_updates[ $file ] : null;

			$plugins[] = array(
				'name'            => (string) $data['Name'],
				'version'         => (string) $data['Version'],
				'plugin_file'     => (string) $file,
				'network_active'  => (bool) $network_active,
				'update_available'=> $update ? true : false,
				'update'          => $this->should_expose_updates() ? ( $update ?: new \stdClass() ) : new \stdClass(),
			);
		}

		$mu_plugins = array();
		$mu         = get_mu_plugins();
		foreach ( $mu as $file => $data ) {
			$mu_plugins[] = array(
				'name'        => (string) $data['Name'],
				'version'     => (string) $data['Version'],
				'plugin_file' => (string) $file,
			);
		}

		$theme_updates = $this->updates->theme_updates();
		$core_updates  = $this->updates->core_updates();

		$flags = array(
			'WP_DEBUG'            => defined( 'WP_DEBUG' ) ? (bool) WP_DEBUG : false,
			'SCRIPT_DEBUG'        => defined( 'SCRIPT_DEBUG' ) ? (bool) SCRIPT_DEBUG : false,
			'DISALLOW_FILE_EDIT'  => defined( 'DISALLOW_FILE_EDIT' ) ? (bool) DISALLOW_FILE_EDIT : false,
			'MULTISITE'           => function_exists( 'is_multisite' ) ? is_multisite() : false,
			'WP_CACHE'            => defined( 'WP_CACHE' ) ? (bool) WP_CACHE : false,
			'WP_ENVIRONMENT_TYPE' => (string) $env_type,
		);

		$result = array(
			'generated_at' => gmdate( 'c' ),
			'site'         => array(
				'home_url' => esc_url_raw( home_url( '/' ) ),
				'site_url' => esc_url_raw( site_url( '/' ) ),
				'multisite'=> (bool) $flags['MULTISITE'],
			),
			'environment'  => array(
				'wp_version'       => (string) $core_version,
				'php_version'      => (string) PHP_VERSION,
				'db_server'        => (string) $db_server,
				'db_version'       => (string) $db_version,
				'environment_type' => (string) $env_type,
				'locale'           => (string) $locale,
			),
			'flags'        => $flags,
			'theme'        => array_merge(
				$theme_arr,
				array(
					'update_available' => isset( $theme_updates[ $theme->get_stylesheet() ] ),
					'update'           => $this->should_expose_updates() && isset( $theme_updates[ $theme->get_stylesheet() ] ) ? $theme_updates[ $theme->get_stylesheet() ] : new \stdClass(),
				)
			),
			'plugins'      => array(
				'active' => $plugins,
				'mu'     => $mu_plugins,
			),
		);

		if ( $this->should_expose_updates() ) {
			$result['updates'] = array(
				'core'   => $core_updates,
				'plugins'=> $plugin_updates,
				'themes' => $theme_updates,
			);
		}

		$this->cache->set( $result );
		return $result;
	}

	/**
	 * Whether settings allow exposing update metadata.
	 *
	 * @return bool
	 */
	private function should_expose_updates() {
		$options = get_option( 'pcc_options', array() );
		return ! empty( $options['expose_updates'] );
	}
}
