<?php
/**
 * WP-CLI command: wp pcc snapshot
 *
 * @package ProjectContextConnector
 */

namespace PCC\CLI;

use PCC\Services\Snapshot_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CLI_Command
 */
class CLI_Command {

	/**
	 * Snapshot builder.
	 *
	 * @var Snapshot_Builder
	 */
	private $snapshot;

	/**
	 * Ctor.
	 *
	 * @param Snapshot_Builder $snapshot Snapshot builder.
	 */
	public function __construct( Snapshot_Builder $snapshot ) {
		$this->snapshot = $snapshot;
	}

	/**
	 * Print the project snapshot JSON.
	 *
	 * ## EXAMPLES
	 *     wp pcc snapshot
	 *
	 * @when after_wp_load
	 * @subcommand snapshot
	 */
	public function snapshot() {
		$data = $this->snapshot->snapshot();
		\WP_CLI::line( wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}
}
