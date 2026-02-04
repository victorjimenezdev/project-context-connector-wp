<?php
/**
 * Main plugin orchestrator.
 *
 * @package ProjectContextConnector
 */

namespace PCC;

use PCC\Admin\Admin;
use PCC\CLI\CLI_Command;
use PCC\REST\Snapshot_REST_Controller;
use PCC\Services\Cache_Manager;
use PCC\Services\CORS_Manager;
use PCC\Services\Rate_Limiter;
use PCC\Services\Snapshot_Builder;
use PCC\Services\Update_Metadata;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap (singleton).
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin
	 */
	private static $instance;

	/**
	 * Services.
	 *
	 * @var Cache_Manager
	 */
	public $cache;

	/**
	 * Services.
	 *
	 * @var Rate_Limiter
	 */
	public $rate_limiter;

	/**
	 * Services.
	 *
	 * @var Update_Metadata
	 */
	public $updates;

	/**
	 * Services.
	 *
	 * @var Snapshot_Builder
	 */
	public $snapshot;

	/**
	 * Services.
	 *
	 * @var CORS_Manager
	 */
	public $cors;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize plugin services and hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->cache        = new Cache_Manager();
		$this->rate_limiter = new Rate_Limiter();
		$this->updates      = new Update_Metadata();
		$this->snapshot     = new Snapshot_Builder( $this->updates, $this->cache );
		$this->cors         = new CORS_Manager();

		// Admin UI.
		if ( is_admin() ) {
			( new Admin( $this->cache ) )->hooks();
		}

		// REST routes.
		add_action(
			'rest_api_init',
			function () {
				( new Snapshot_REST_Controller( $this->snapshot, $this->rate_limiter, $this->cors ) )->register_routes();
			}
		);

		// Invalidate cache on relevant changes.
		$this->register_invalidation_hooks();

		// WP-CLI.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'pcc', new CLI_Command( $this->snapshot ) );
		}
	}

	/**
	 * Register cache invalidation hooks.
	 *
	 * @return void
	 */
	private function register_invalidation_hooks() {
		add_action( 'activated_plugin', array( $this->cache, 'purge' ) );
		add_action( 'deactivated_plugin', array( $this->cache, 'purge' ) );
		add_action( 'deleted_plugin', array( $this->cache, 'purge' ) );
		add_action( 'switch_theme', array( $this->cache, 'purge' ) );
		add_action( 'upgrader_process_complete', array( $this->cache, 'purge' ) );
	}
}
