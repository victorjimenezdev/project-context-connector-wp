<?php
/**
 * Settings UI (accessible).
 *
 * @package ProjectContextConnector
 *
 * @var array $options
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wrap" role="region" aria-labelledby="pcc-heading">
	<h1 id="pcc-heading"><?php esc_html_e( 'Project Context Connector', 'project-context-connector' ); ?></h1>

	<p><?php esc_html_e( 'Configure CORS, rate limiting, caching, update metadata, and optional allow-lists. No secrets are stored in the database.', 'project-context-connector' ); ?></p>

	<form action="options.php" method="post" novalidate>
		<?php
		settings_fields( 'pcc_settings' );
		do_settings_sections( 'pcc_settings' );
		submit_button( __( 'Save Changes', 'project-context-connector' ) );
		?>
	</form>

	<hr />

	<h2><?php esc_html_e( 'HMAC Keys (Server-to-Server Access)', 'project-context-connector' ); ?></h2>
	<p><?php esc_html_e( 'Define secrets only in wp-config.php. You can generate a key id and secret here for copy/paste. The secret is displayed once and not stored.', 'project-context-connector' ); ?></p>

	<div id="pcc-keygen" aria-live="polite" role="status">
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'pcc_generate_key' ); ?>
			<input type="hidden" name="action" value="pcc_generate_key" />
			<?php submit_button( __( 'Generate Key', 'project-context-connector' ), 'secondary', 'submit', false ); ?>
		</form>

		<?php if ( isset( $_GET['pcc_key'], $_GET['pcc_secret'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php
			$key_id = sanitize_text_field( wp_unslash( $_GET['pcc_key'] ) ); // phpcs:ignore
			$secret = sanitize_text_field( wp_unslash( $_GET['pcc_secret'] ) ); // phpcs:ignore
			?>
			<div class="notice notice-success" tabindex="0">
				<p><strong><?php esc_html_e( 'New key generated. Copy the values below now.', 'project-context-connector' ); ?></strong></p>
				<label for="pcc-key-id"><?php esc_html_e( 'Key ID', 'project-context-connector' ); ?></label>
				<input id="pcc-key-id" class="regular-text" type="text" readonly value="<?php echo esc_attr( $key_id ); ?>" />
				<button type="button" class="button" data-copy-target="#pcc-key-id"><?php esc_html_e( 'Copy', 'project-context-connector' ); ?></button>

				<label for="pcc-secret" style="display:block;margin-top:8px;"><?php esc_html_e( 'Secret (displayed once)', 'project-context-connector' ); ?></label>
				<input id="pcc-secret" class="regular-text" type="text" readonly value="<?php echo esc_attr( $secret ); ?>" />
				<button type="button" class="button" data-copy-target="#pcc-secret"><?php esc_html_e( 'Copy', 'project-context-connector' ); ?></button>

				<p><?php esc_html_e( 'Add to wp-config.php (choose one):', 'project-context-connector' ); ?></p>
				<pre tabindex="0"><?php echo esc_html( "define('PCC_HMAC_KEYS_JSON', '{" . $key_id . "': '" . $secret . "'}');" ); ?></pre>
				<pre tabindex="0"><?php echo esc_html( "define('PCC_HMAC_KEY_" . $key_id . "', '" . $secret . "');" ); ?></pre>
			</div>
		<?php endif; ?>
	</div>

	<script>
		/* eslint-disable no-undef */
		(function(){
			document.addEventListener('click', function(e){
				var btn = e.target.closest('[data-copy-target]');
				if(!btn){return;}
				var sel = btn.getAttribute('data-copy-target');
				var el = document.querySelector(sel);
				if(!el){return;}
				el.select(); el.setSelectionRange(0, 99999);
				try {
					document.execCommand('copy');
					btn.textContent = '<?php echo esc_js( __( 'Copied', 'project-context-connector' ) ); ?>';
					setTimeout(function(){ btn.textContent = '<?php echo esc_js( __( 'Copy', 'project-context-connector' ) ); ?>'; }, 1500);
				} catch (err) {}
			});

			// HTTP origin warning.
			var originsField = document.getElementById('allowed_origins');
			if (originsField) {
				originsField.addEventListener('blur', function() {
					var origins = this.value;
					var warningEl = document.querySelector('.http-warning');
					if (origins.toLowerCase().indexOf('http://') !== -1) {
						if (!warningEl) {
							var warning = document.createElement('p');
							warning.className = 'http-warning';
							warning.style.color = '#d63638';
							warning.innerHTML = '<strong><?php echo esc_js( __( 'Warning', 'project-context-connector' ) ); ?>:</strong> <?php echo esc_js( __( 'HTTP origins are insecure. Use HTTPS in production.', 'project-context-connector' ) ); ?>';
							this.parentNode.appendChild(warning);
						}
					} else {
						if (warningEl) {
							warningEl.remove();
						}
					}
				});
			}
		})();
	</script>
	<style>
		/* Visible focus outline and spacing */
		#pcc-keygen .notice:focus{ outline: 2px solid #2271b1; }
		label{ display:block; margin-top:6px; }
	</style>

	<hr />
	<h2><?php esc_html_e( 'Examples', 'project-context-connector' ); ?></h2>
	<p><strong>Basic Auth via Application Passwords</strong></p>
	<pre>GET /wp-json/pcc/v1/snapshot
Authorization: Basic base64(&lt;username&gt;:&lt;application-password&gt;)</pre>

	<p><strong>HMAC-signed</strong></p>
	<pre>GET /wp-json/pcc/v1/snapshot/signed
X-PCC-Key: &lt;key id&gt;
X-PCC-Timestamp: &lt;unix seconds&gt;
X-PCC-Signature: hex(hmac_sha256("&lt;METHOD&gt;\n&lt;PATH&gt;\n&lt;TIMESTAMP&gt;", secret))</pre>
</div>
