<?php
/**
 * Admin settings for Site Contextsnap.
 *
 * @package SiteContextsnap
 */

namespace PCC\Admin;

use PCC\Services\Cache_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings page using Settings API.
 */
class Admin {

	/**
	 * Option name.
	 */
	const OPTION = 'pcc_options';

	/**
	 * Cache manager.
	 *
	 * @var Cache_Manager
	 */
	private $cache;

	/**
	 * Constructor.
	 *
	 * @param Cache_Manager $cache Cache manager.
	 */
	public function __construct( Cache_Manager $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Register admin hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_pcc_generate_key', array( $this, 'handle_generate_key' ) );
	}

	/**
	 * Add the settings page under Settings.
	 *
	 * @return void
	 */
	public function menu() {
		add_options_page(
			__( 'Site Contextsnap', 'site-contextsnap' ),
			__( 'Site Contextsnap', 'site-contextsnap' ),
			'manage_options',
			'site-contextsnap',
			array( $this, 'render' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'pcc_settings',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(
					'cors_enabled'            => false,
					'allowed_origins'         => array(),
					'rate_limit_threshold'    => 60,
					'rate_limit_window'       => 60,
					'cache_ttl'               => 300,
					'expose_updates'          => false,
					'expose_database_version' => true,
					'allow_caps'              => array(),
					'allow_user_ids'          => array(),
					'allow_ips'               => array(),
					'allow_bearer'            => false,
				),
			)
		);

		add_settings_section(
			'pcc_general',
			__( 'General', 'site-contextsnap' ),
			'__return_false',
			'pcc_settings'
		);
		add_settings_field( 'cors_enabled', __( 'Enable CORS', 'site-contextsnap' ), array( $this, 'field_checkbox' ), 'pcc_settings', 'pcc_general', array( 'key' => 'cors_enabled' ) );
		add_settings_field( 'allowed_origins', __( 'Allowed Origins (CORS)', 'site-contextsnap' ), array( $this, 'field_textarea' ), 'pcc_settings', 'pcc_general', array( 'key' => 'allowed_origins', 'placeholder' => "https://example.com\nhttps://ci.local" ) );
		add_settings_field( 'rate_limit_threshold', __( 'Rate limit: requests per window', 'site-contextsnap' ), array( $this, 'field_number' ), 'pcc_settings', 'pcc_general', array( 'key' => 'rate_limit_threshold', 'min' => 1 ) );
		add_settings_field( 'rate_limit_window', __( 'Rate limit window (seconds)', 'site-contextsnap' ), array( $this, 'field_number' ), 'pcc_settings', 'pcc_general', array( 'key' => 'rate_limit_window', 'min' => 1 ) );
		add_settings_field( 'cache_ttl', __( 'Cache max age (seconds)', 'site-contextsnap' ), array( $this, 'field_number' ), 'pcc_settings', 'pcc_general', array( 'key' => 'cache_ttl', 'min' => 0 ) );
		add_settings_field( 'expose_updates', __( 'Expose update metadata', 'site-contextsnap' ), array( $this, 'field_checkbox' ), 'pcc_settings', 'pcc_general', array( 'key' => 'expose_updates' ) );
		add_settings_field( 'expose_database_version', __( 'Expose database version', 'site-contextsnap' ), array( $this, 'field_checkbox_with_description' ), 'pcc_settings', 'pcc_general', array( 'key' => 'expose_database_version', 'description' => __( 'Include database driver and version. Disable for minimal information disclosure.', 'site-contextsnap' ) ) );

		add_settings_section(
			'pcc_access',
			__( 'Access Control', 'site-contextsnap' ),
			function () {
				echo '<p>' . esc_html__( 'In addition to the capability pcc_read_snapshot (mapped to manage_options by default), optionally allow specific capabilities, user IDs, and IP addresses.', 'site-contextsnap' ) . '</p>';
			},
			'pcc_settings'
		);
		add_settings_field( 'allow_caps', __( 'Allow capabilities (one per line)', 'site-contextsnap' ), array( $this, 'field_textarea' ), 'pcc_settings', 'pcc_access', array( 'key' => 'allow_caps', 'placeholder' => "manage_options\nview_site_health" ) );
		add_settings_field( 'allow_user_ids', __( 'Allow user IDs (comma or newline separated)', 'site-contextsnap' ), array( $this, 'field_textarea' ), 'pcc_settings', 'pcc_access', array( 'key' => 'allow_user_ids', 'placeholder' => "1\n42" ) );
		add_settings_field( 'allow_ips', __( 'Allow IPs (exact matches, one per line)', 'site-contextsnap' ), array( $this, 'field_textarea' ), 'pcc_settings', 'pcc_access', array( 'key' => 'allow_ips', 'placeholder' => "127.0.0.1\n203.0.113.12" ) );
		add_settings_field( 'allow_bearer', __( 'Allow Bearer auth (if a JWT/OAuth plugin sets the current user)', 'site-contextsnap' ), array( $this, 'field_checkbox' ), 'pcc_settings', 'pcc_access', array( 'key' => 'allow_bearer' ) );
	}

	/**
	 * Sanitize and normalize options.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$out = array();

		$out['cors_enabled'] = ! empty( $input['cors_enabled'] );

		$origins = array();
		if ( ! empty( $input['allowed_origins'] ) ) {
			$lines = is_array( $input['allowed_origins'] ) ? $input['allowed_origins'] : preg_split( '/\r\n|\r|\n|,/', (string) $input['allowed_origins'] );
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( '' !== $line ) {
					$origins[] = esc_url_raw( $line );
				}
			}
		}
		$out['allowed_origins'] = array_values( array_unique( $origins ) );

		$out['rate_limit_threshold']    = max( 1, (int) ( $input['rate_limit_threshold'] ?? 60 ) );
		$out['rate_limit_window']       = max( 1, (int) ( $input['rate_limit_window'] ?? 60 ) );
		$out['cache_ttl']               = max( 0, (int) ( $input['cache_ttl'] ?? 300 ) );
		$out['expose_updates']          = ! empty( $input['expose_updates'] );
		$out['expose_database_version'] = ! empty( $input['expose_database_version'] );

		// Allow capabilities.
		$caps = array();
		if ( ! empty( $input['allow_caps'] ) ) {
			$lines = is_array( $input['allow_caps'] ) ? $input['allow_caps'] : preg_split( '/\r\n|\r|\n|,/', (string) $input['allow_caps'] );
			foreach ( $lines as $line ) {
				$cap = sanitize_key( (string) $line );
				if ( $cap ) {
					$caps[] = $cap;
				}
			}
		}
		$out['allow_caps'] = array_values( array_unique( $caps ) );

		// Allow user IDs.
		$user_ids = array();
		if ( ! empty( $input['allow_user_ids'] ) ) {
			$lines = is_array( $input['allow_user_ids'] ) ? $input['allow_user_ids'] : preg_split( '/\r\n|\r|\n|,/', (string) $input['allow_user_ids'] );
			foreach ( $lines as $line ) {
				$id = (int) trim( (string) $line );
				if ( $id > 0 ) {
					$user_ids[] = $id;
				}
			}
		}
		$out['allow_user_ids'] = array_values( array_unique( $user_ids ) );

		// Allow IPs.
		$ips = array();
		if ( ! empty( $input['allow_ips'] ) ) {
			$lines = is_array( $input['allow_ips'] ) ? $input['allow_ips'] : preg_split( '/\r\n|\r|\n|,/', (string) $input['allow_ips'] );
			foreach ( $lines as $line ) {
				$ip = trim( (string) $line );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$ips[] = $ip;
				}
			}
		}
		$out['allow_ips']    = array_values( array_unique( $ips ) );
		$out['allow_bearer'] = ! empty( $input['allow_bearer'] );

		// Purge cache when relevant options change.
		$this->cache->purge();

		return $out;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'site-contextsnap' ) );
		}
		$options = get_option( self::OPTION, array() );
		require __DIR__ . '/views/settings-page.php';
	}

	/**
	 * Handle key generation (display-once secret).
	 *
	 * @return void
	 */
	public function handle_generate_key() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'site-contextsnap' ) );
		}
		check_admin_referer( 'pcc_generate_key' );

		$key_id = 'key_' . wp_generate_password( 8, false, false );
		$bytes  = random_bytes( 32 );
		$secret = bin2hex( $bytes );

		// Redirect back with one-time values in query string (not stored).
		$redirect = add_query_arg(
			array(
				'page'      => 'site-contextsnap',
				'pcc_key'   => rawurlencode( $key_id ),
				'pcc_secret'=> rawurlencode( $secret ),
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $redirect );
		exit;
	}

	/* ===== Field renderers (accessible, labeled) ===== */

	/**
	 * Checkbox field.
	 *
	 * @param array $args Args with 'key'.
	 */
	public function field_checkbox( $args ) {
		$key     = $args['key'];
		$options = get_option( self::OPTION, array() );
		$checked = ! empty( $options[ $key ] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="1" <?php checked( $checked ); ?> />
			<?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ); ?>
		</label>
		<?php
	}

	/**
	 * Number field.
	 *
	 * @param array $args Args with 'key' and optional 'min'.
	 */
	public function field_number( $args ) {
		$key     = $args['key'];
		$min     = isset( $args['min'] ) ? (int) $args['min'] : 0;
		$options = get_option( self::OPTION, array() );
		$value   = isset( $options[ $key ] ) ? (int) $options[ $key ] : 0;
		?>
		<input type="number" min="<?php echo esc_attr( (string) $min ); ?>" id="<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( (string) $value ); ?>" class="small-text" />
		<?php
	}

	/**
	 * Textarea field.
	 *
	 * @param array $args Args with 'key' and optional 'placeholder'.
	 */
	public function field_textarea( $args ) {
		$key         = $args['key'];
		$placeholder = isset( $args['placeholder'] ) ? (string) $args['placeholder'] : '';
		$options     = get_option( self::OPTION, array() );
		$value       = $options[ $key ] ?? '';
		if ( is_array( $value ) ) {
			$value = implode( "\n", array_map( 'strval', $value ) );
		}
		?>
		<textarea rows="5" cols="60" id="<?php echo esc_attr( $key ); ?>"
			name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( (string) $value ); ?></textarea>
		<?php
	}

	/**
	 * Checkbox field with description.
	 *
	 * @param array $args Args with 'key' and 'description'.
	 */
	public function field_checkbox_with_description( $args ) {
		$key         = $args['key'];
		$description = isset( $args['description'] ) ? (string) $args['description'] : '';
		$options     = get_option( self::OPTION, array() );
		$checked     = ! empty( $options[ $key ] );
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION . '[' . $key . ']' ); ?>" value="1" <?php checked( $checked ); ?> />
			<?php echo esc_html( ucwords( str_replace( '_', ' ', (string) $key ) ) ); ?>
		</label>
		<?php if ( $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php endif; ?>
		<?php
	}
}
