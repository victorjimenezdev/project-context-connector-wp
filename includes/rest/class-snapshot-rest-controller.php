<?php
/**
 * REST Controller for /pcc/v1/snapshot and /pcc/v1/snapshot/signed.
 *
 * @package ProjectContextConnector
 */

namespace PCC\REST;

use PCC\Services\CORS_Manager;
use PCC\Services\Rate_Limiter;
use PCC\Services\Snapshot_Builder;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Snapshot REST controller.
 */
class Snapshot_REST_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'pcc/v1';

	/**
	 * Rest base.
	 *
	 * @var string
	 */
	protected $rest_base = 'snapshot';

	/**
	 * Services.
	 *
	 * @var Snapshot_Builder
	 */
	private $snapshot;

	/**
	 * Services.
	 *
	 * @var Rate_Limiter
	 */
	private $rate;

	/**
	 * Services.
	 *
	 * @var CORS_Manager
	 */
	private $cors;

	/**
	 * Constructor.
	 *
	 * @param Snapshot_Builder $snapshot Snapshot builder.
	 * @param Rate_Limiter     $rate     Rate limiter.
	 * @param CORS_Manager     $cors     CORS manager.
	 */
	public function __construct( Snapshot_Builder $snapshot, Rate_Limiter $rate, CORS_Manager $cors ) {
		$this->snapshot = $snapshot;
		$this->rate     = $rate;
		$this->cors     = $cors;
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_snapshot' ),
					'permission_callback' => '__return_true',
					'args'                => array(), // Read-only, no args.
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/signed',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_snapshot_signed' ),
					'permission_callback' => '__return_true', // Signature check inside.
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * GET /snapshot handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_snapshot( WP_REST_Request $request ) {
		$throttled = $this->maybe_throttle_request( $request );
		if ( $throttled ) {
			return $throttled;
		}

		$auth = $this->authorize_snapshot_request( $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}

		$data = $this->snapshot->snapshot();
		return $this->respond_ok( $data );
	}

	/**
	 * GET /snapshot/signed (HMAC signed) handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_snapshot_signed( WP_REST_Request $request ) {
		$throttled = $this->maybe_throttle_request( $request );
		if ( $throttled ) {
			return $throttled;
		}

		$ip_denied = $this->ensure_ip_allowed( $request );
		if ( is_wp_error( $ip_denied ) ) {
			return $ip_denied;
		}

		$key_id    = (string) $request->get_header( 'x-pcc-key' );
		$timestamp = (string) $request->get_header( 'x-pcc-timestamp' );
		$signature = strtolower( (string) $request->get_header( 'x-pcc-signature' ) );

		if ( '' === $key_id || '' === $timestamp || '' === $signature ) {
			return new WP_Error( 'pcc_bad_signature', __( 'Missing HMAC headers.', 'project-context-connector' ), array( 'status' => 401 ) );
		}

		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return new WP_Error( 'pcc_bad_signature', __( 'Timestamp outside acceptable window.', 'project-context-connector' ), array( 'status' => 401 ) );
		}

		$secret = $this->lookup_hmac_secret( $key_id );
		if ( '' === $secret ) {
			return new WP_Error( 'pcc_bad_signature', __( 'Unknown key id.', 'project-context-connector' ), array( 'status' => 401 ) );
		}

		$method = strtoupper( $request->get_method() );
		$path   = '/wp-json' . untrailingslashit( $request->get_route() );
		$data   = $method . "\n" . $path . "\n" . $timestamp;

		$expected = hash_hmac( 'sha256', $data, $secret );
		if ( ! hash_equals( $expected, $signature ) ) {
			return new WP_Error( 'pcc_bad_signature', __( 'Invalid HMAC signature.', 'project-context-connector' ), array( 'status' => 401 ) );
		}

		// Signature OK: return snapshot.
		return $this->respond_ok( $this->snapshot->snapshot() );
	}

	/**
	 * Build 200 JSON response with cache headers.
	 *
	 * @param array $data Snapshot.
	 * @return WP_REST_Response
	 */
	private function respond_ok( array $data ) {
		$resp = new WP_REST_Response( $data );
		$resp->set_status( 200 );
		// Cache-friendly headers for intermediaries (data itself is already cached internally).
		$ttl = (int) ( get_option( 'pcc_options', array() )['cache_ttl'] ?? 300 );
		$resp->header( 'Cache-Control', 'public, max-age=' . max( 0, $ttl ) );
		return $resp;
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
	 * @return string Secret or empty string.
	 */
	private function lookup_hmac_secret( $key_id ) {
		$map = array();

		if ( defined( 'PCC_HMAC_KEYS_JSON' ) ) {
			$j = json_decode( (string) constant( 'PCC_HMAC_KEYS_JSON' ), true );
			if ( is_array( $j ) ) {
				$map = array_merge( $map, $j );
			}
		}
		if ( defined( 'PCC_HMAC_KEYS' ) ) {
			$j = json_decode( (string) constant( 'PCC_HMAC_KEYS' ), true );
			if ( is_array( $j ) ) {
				$map = array_merge( $map, $j );
			}
		}

		// Individual constants PCC_HMAC_KEY_<ID>.
		$consts = get_defined_constants( true );
		if ( isset( $consts['user'] ) && is_array( $consts['user'] ) ) {
			foreach ( $consts['user'] as $name => $value ) {
				if ( 0 === strpos( $name, 'PCC_HMAC_KEY_' ) ) {
					$id           = substr( $name, strlen( 'PCC_HMAC_KEY_' ) );
					$map[ $id ] = (string) $value;
				}
			}
		}

		return isset( $map[ $key_id ] ) ? (string) $map[ $key_id ] : '';
	}

	/**
	 * JSON Schema of the response.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'pcc_snapshot',
			'type'       => 'object',
			'properties' => array(
				'generated_at' => array( 'type' => 'string' ),
				'site'         => array( 'type' => 'object' ),
				'environment'  => array( 'type' => 'object' ),
				'flags'        => array( 'type' => 'object' ),
				'theme'        => array( 'type' => 'object' ),
				'plugins'      => array( 'type' => 'object' ),
				'updates'      => array( 'type' => 'object' ),
			),
		);
	}

	/**
	 * Apply rate limiting and send Retry-After headers when necessary.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|null
	 */
	private function maybe_throttle_request( WP_REST_Request $request ) {
		$rl = $this->rate->check( $this->rate->key_from_request( $request ) );
		if ( $rl['allowed'] ) {
			return null;
		}

		$response = new WP_REST_Response(
			array(
				'error'       => 'rate_limited',
				'retry_after' => $rl['retry_after'],
			)
		);
		$response->set_status( 429 );
		$response->header( 'Retry-After', (string) $rl['retry_after'] );
		return $response;
	}

	/**
	 * Ensure the request IP is not blocked.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|null
	 */
	private function ensure_ip_allowed( WP_REST_Request $request ) {
		$ip = function_exists( 'rest_get_ip_address' ) ? rest_get_ip_address() : ( $_SERVER['REMOTE_ADDR'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( $this->cors->is_ip_allowed( (string) $ip ) ) {
			return null;
		}
		return new WP_Error( 'pcc_forbidden_ip', __( 'Access denied for this IP address.', 'project-context-connector' ), array( 'status' => 403 ) );
	}

	/**
	 * Authorize the authenticated user against plugin settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_Error|null
	 */
	private function authorize_snapshot_request( WP_REST_Request $request ) {
		$ip_denied = $this->ensure_ip_allowed( $request );
		if ( is_wp_error( $ip_denied ) ) {
			return $ip_denied;
		}

		$options = get_option( 'pcc_options', array() );
		$auth    = (string) $request->get_header( 'authorization' );
		if ( $auth && 0 === stripos( $auth, 'Bearer ' ) && empty( $options['allow_bearer'] ) ) {
			return new WP_Error( 'pcc_bearer_disabled', __( 'Bearer authentication is disabled for this endpoint.', 'project-context-connector' ), array( 'status' => 401 ) );
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'pcc_unauthorized', __( 'Authentication required.', 'project-context-connector' ), array( 'status' => 401 ) );
		}

		if ( current_user_can( 'pcc_read_snapshot' ) ) {
			return null;
		}

		$allow_users = isset( $options['allow_user_ids'] ) ? array_map( 'intval', (array) $options['allow_user_ids'] ) : array();
		if ( in_array( (int) $user_id, $allow_users, true ) ) {
			return null;
		}

		$allow_caps = isset( $options['allow_caps'] ) ? array_map( 'sanitize_key', (array) $options['allow_caps'] ) : array();
		foreach ( $allow_caps as $cap ) {
			if ( $cap && current_user_can( $cap ) ) {
				return null;
			}
		}

		return new WP_Error( 'pcc_forbidden', __( 'You do not have permission to view the project snapshot.', 'project-context-connector' ), array( 'status' => 403 ) );
	}

}
