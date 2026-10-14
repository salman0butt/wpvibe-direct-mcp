<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPVDMCP_Admin {
	private const TOKEN_MUTATION_CLAIM = 'wpvdmcp_token_mutation_claim';

	private static $instance;

	public static function instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
		add_action( 'admin_post_wpvdmcp_generate_token', array( $this, 'generate_token' ) );
		add_action( 'admin_post_wpvdmcp_revoke_token', array( $this, 'revoke_token' ) );
		add_action( 'admin_post_wpvdmcp_save', array( $this, 'save' ) );
		add_action( 'admin_post_wpvdmcp_approval', array( $this, 'approval_action' ) );
		add_action( 'admin_init', array( $this, 'redirect_after_activation' ) );
	}

	public function menu() {
		if ( defined( 'WPVIBE_VERSION' ) || class_exists( 'WPVibe_Admin' ) ) {
			add_submenu_page( 'vibe-ai', 'Direct MCP', 'Direct MCP', 'manage_options', 'wpvibe-direct-mcp', array( $this, 'render' ) );
		} else {
			add_menu_page( 'WPVibe Direct MCP', 'Direct MCP', 'manage_options', 'wpvibe-direct-mcp', array( $this, 'render' ), 'dashicons-rest-api', 59 );
		}
	}

	public function redirect_after_activation() {
		if ( get_transient( 'wpvdmcp_activation_redirect' ) ) {
			delete_transient( 'wpvdmcp_activation_redirect' );
			if ( current_user_can( 'activate_plugins' ) && ! is_network_admin() && ! isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only activation redirect.
				wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp' ) );
				exit;
			}
		}
	}

	public function generate_token() {
		$this->guard();
		$claim = $this->acquire_token_mutation_claim();
		if ( false === $claim ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_error=1' ) );
			exit;
		}
		$old_hash = get_option( 'wpvdmcp_token_hash', null );
		$old_user = get_option( 'wpvdmcp_user_id', null );
		$token = 'wpvd_' . wp_generate_password( 64, false, false );
		$hash = hash( 'sha256', $token );
		$user_id = get_current_user_id();
		update_option( 'wpvdmcp_token_hash', $hash, false );
		if ( $hash !== get_option( 'wpvdmcp_token_hash', null ) ) {
			$this->restore_token_state( $old_hash, $old_user );
			$this->release_token_mutation_claim( $claim );
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_error=1' ) );
			exit;
		}
		update_option( 'wpvdmcp_user_id', $user_id, false );
		if ( $user_id !== (int) get_option( 'wpvdmcp_user_id', 0 ) ) {
			$this->restore_token_state( $old_hash, $old_user );
			$this->release_token_mutation_claim( $claim );
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_error=1' ) );
			exit;
		}
		if ( ! set_transient( 'wpvdmcp_new_token_' . $user_id, $token, 5 * MINUTE_IN_SECONDS ) ) {
			$this->restore_token_state( $old_hash, $old_user );
			$this->release_token_mutation_claim( $claim );
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_error=1' ) );
			exit;
		}
		$this->release_token_mutation_claim( $claim );
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_generated=1' ) );
		exit;
	}

	private function restore_token_state( $hash, $user_id ) {
		if ( null === $user_id ) {
			delete_option( 'wpvdmcp_user_id' );
		} else {
			update_option( 'wpvdmcp_user_id', $user_id, false );
		}
		if ( $user_id !== get_option( 'wpvdmcp_user_id', null ) ) {
			return;
		}
		if ( null === $hash ) {
			delete_option( 'wpvdmcp_token_hash' );
		} else {
			update_option( 'wpvdmcp_token_hash', $hash, false );
		}
	}

	private function acquire_token_mutation_claim() {
		$claim = wp_generate_password( 32, false, false );
		if ( ! add_option( self::TOKEN_MUTATION_CLAIM, $claim, '', false ) ) {
			return false;
		}
		return $claim;
	}

	private function release_token_mutation_claim( $claim ) {
		if ( is_string( $claim ) && '' !== $claim && $claim === get_option( self::TOKEN_MUTATION_CLAIM, null ) ) {
			delete_option( self::TOKEN_MUTATION_CLAIM );
		}
	}

	public function revoke_token() {
		$this->guard();
		$claim = $this->acquire_token_mutation_claim();
		if ( false === $claim ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_revoke_error=1' ) );
			exit;
		}
		delete_option( 'wpvdmcp_token_hash' );
		delete_option( 'wpvdmcp_user_id' );
		if ( null !== get_option( 'wpvdmcp_token_hash', null ) || null !== get_option( 'wpvdmcp_user_id', null ) ) {
			update_option( 'wpvdmcp_token_hash', '', false );
			update_option( 'wpvdmcp_user_id', 0, false );
			if ( '' !== get_option( 'wpvdmcp_token_hash', null ) || 0 !== (int) get_option( 'wpvdmcp_user_id', -1 ) ) {
				$this->release_token_mutation_claim( $claim );
				wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_revoke_error=1' ) );
				exit;
			}
		}
		$this->release_token_mutation_claim( $claim );
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_revoked=1' ) );
		exit;
	}

	public function save() {
		$this->guard();
		$old_enabled = get_option( 'wpvdmcp_enabled', null );
		$old_query = get_option( 'wpvdmcp_allow_query_token', null );
		$enabled = isset( $_POST['enabled'] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() verifies nonce.
		$query = isset( $_POST['allow_query_token'] ) ? '1' : '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard() verifies nonce.
		update_option( 'wpvdmcp_enabled', $enabled, false );
		update_option( 'wpvdmcp_allow_query_token', $query, false );
		if ( $enabled !== get_option( 'wpvdmcp_enabled', null ) || $query !== get_option( 'wpvdmcp_allow_query_token', null ) ) {
			$this->restore_setting_state( 'wpvdmcp_enabled', $old_enabled );
			$this->restore_setting_state( 'wpvdmcp_allow_query_token', $old_query );
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&settings_error=1' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&settings_updated=1' ) );
		exit;
	}

	private function restore_setting_state( $key, $value ) {
		if ( null === $value ) {
			delete_option( $key );
		} else {
			update_option( $key, $value, false );
		}
	}

	/** Browser-only action behind cookie auth + nonce for Direct MCP approvals. */
	public function approval_action() {
		$this->guard();
		$id       = isset( $_POST['approval_id'] ) && is_string( $_POST['approval_id'] ) ? sanitize_text_field( wp_unslash( $_POST['approval_id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard().
		$decision = isset( $_POST['decision'] ) && is_string( $_POST['decision'] ) ? sanitize_key( wp_unslash( $_POST['decision'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- guard().
		if ( ! in_array( $decision, array( 'approve', 'reject' ), true ) ) {
			$result = new WP_Error( 'invalid_approval_decision', 'Approval decision must be approve or reject.', array( 'status' => 400 ) );
		} else {
			$result = 'approve' === $decision ? WPVDMCP_Approvals::approve( $id, get_current_user_id() ) : WPVDMCP_Approvals::reject( $id, get_current_user_id() );
		}
		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&approval=' . rawurlencode( $id ) . '&approval_error=1' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&approval=' . rawurlencode( $id ) . '&approval_saved=1' ) );
		exit;
	}

	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'wpvdmcp_action' );
	}

	/** Secret-free status payload used by wp-admin and tests. */
	public static function status_snapshot() {
		$capabilities = WPVDMCP_Compatibility::capability_summary();
		$wpvibe       = WPVDMCP_Compatibility::wpvibe_version();
		$warning      = '';
		if ( $wpvibe && version_compare( $wpvibe, '1.20.3', '>' ) ) {
			$warning = 'Installed WPVibe is newer than the latest version verified by this Direct MCP release (1.20.3). Feature detection remains active, but review compatibility documentation after upgrading.';
		}
		return array(
			'direct_version'        => defined( 'WPVDMCP_VERSION' ) ? WPVDMCP_VERSION : '',
			'wpvibe_version'        => $wpvibe,
			'wordpress_version'     => WPVDMCP_Compatibility::wordpress_version(),
			'endpoint'              => rest_url( 'wpvibe-direct/v1/mcp' ),
			'health_endpoint'       => rest_url( 'wpvibe-direct/v1/health' ),
			'enabled'               => '1' === get_option( 'wpvdmcp_enabled', '1' ),
			'authentication_configured' => (bool) get_option( 'wpvdmcp_token_hash', '' ),
			'query_token_enabled'    => '1' === get_option( 'wpvdmcp_allow_query_token', '0' ),
			'token_owner_user_id'   => (int) get_option( 'wpvdmcp_user_id', 0 ),
			'last_request'          => (int) get_option( 'wpvdmcp_last_active', 0 ),
			'tool_count'            => count( WPVDMCP_Tools::definitions() ),
			'capabilities'          => $capabilities,
			'compatibility_warning' => $warning,
			'media'                 => array(
				'public_url'    => WPVDMCP_Compatibility::route_exists( '/wpvibe/v1/upload-media' ),
				'device_browser'=> class_exists( 'WPVDMCP_Upload' ),
				'svg_sanitizer' => class_exists( 'WPVibe_SVG_Sanitizer' ),
			),
		);
	}

	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$status     = self::status_snapshot();
		$token      = get_transient( 'wpvdmcp_new_token_' . get_current_user_id() );
		if ( $token ) {
			delete_transient( 'wpvdmcp_new_token_' . get_current_user_id() );
		}
		$user_id = (int) $status['token_owner_user_id'];
		$user    = $user_id ? get_user_by( 'id', $user_id ) : null;
		$log     = get_option( 'wpvdmcp_activity', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$approval_id = isset( $_GET['approval'] ) && is_string( $_GET['approval'] ) ? sanitize_text_field( wp_unslash( $_GET['approval'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status display only.
		?>
		<div class="wrap">
			<h1>WPVibe Direct MCP</h1>
			<p style="max-width:900px;font-size:15px">A self-hosted MCP transport that reuses the installed WPVibe plugin’s protected WordPress routes. Capability checks, draft-theme safety, builder-native saves, WP-CLI restrictions, SVG sanitization, and upstream approvals remain authoritative.</p>

			<?php if ( ! empty( $status['compatibility_warning'] ) ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( $status['compatibility_warning'] ); ?></p></div>
			<?php endif; ?>
				<?php if ( ! defined( 'WPVIBE_VERSION' ) && ! class_exists( 'WPVibe_REST' ) ) : ?>
					<div class="notice notice-error"><p><strong>WPVibe is not active.</strong> Install and activate WPVibe before using route-backed tools.</p></div>
				<?php endif; ?>
				<?php if ( isset( $_GET['token_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status display only. ?>
					<div class="notice notice-error"><p><strong>The new Direct MCP token could not be saved.</strong> The previous credential state was restored; try again after checking WordPress database writes.</p></div>
				<?php endif; ?>
				<?php if ( isset( $_GET['token_revoke_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status display only. ?>
					<div class="notice notice-error"><p><strong>The Direct MCP token could not be fully revoked.</strong> Treat the existing token as potentially active until storage is healthy and revocation succeeds.</p></div>
				<?php endif; ?>
				<?php if ( isset( $_GET['settings_error'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- status display only. ?>
					<div class="notice notice-error"><p><strong>Direct MCP settings could not be saved.</strong> The previous settings were restored.</p></div>
				<?php endif; ?>
				<?php if ( $user && ! user_can( $user, 'manage_options' ) ) : ?>
				<div class="notice notice-error"><p><strong>The Direct MCP token owner is no longer an Administrator.</strong> Regenerate the token from an administrator account before using the endpoint.</p></div>
			<?php endif; ?>
			<?php if ( $token ) : ?>
				<div class="notice notice-success"><p><strong>Copy this token now. It will not be shown again.</strong></p><p><input id="wpvd-token" type="text" readonly value="<?php echo esc_attr( $token ); ?>" style="width:100%;max-width:900px;font-family:monospace"><button class="button" type="button" onclick="navigator.clipboard.writeText(document.getElementById('wpvd-token').value)">Copy token</button></p></div>
			<?php endif; ?>

			<?php $this->render_approval( $approval_id ); ?>

			<table class="widefat striped" style="max-width:1100px;margin-top:20px"><tbody>
				<tr><th style="width:240px">Direct MCP version</th><td><?php echo esc_html( $status['direct_version'] ); ?></td></tr>
				<tr><th>WPVibe version</th><td><?php echo $status['wpvibe_version'] ? esc_html( $status['wpvibe_version'] ) : 'Not detected'; ?></td></tr>
				<tr><th>WordPress version</th><td><?php echo esc_html( $status['wordpress_version'] ); ?></td></tr>
				<tr><th>Status</th><td><?php echo $status['enabled'] ? '<span style="color:#008a20;font-weight:700">Enabled</span>' : '<span style="color:#b32d2e;font-weight:700">Disabled</span>'; ?></td></tr>
				<tr><th>MCP endpoint</th><td><code><?php echo esc_html( $status['endpoint'] ); ?></code></td></tr>
				<tr><th>Health endpoint</th><td><code><?php echo esc_html( $status['health_endpoint'] ); ?></code></td></tr>
				<tr><th>Authentication</th><td>Bearer token <?php echo $status['authentication_configured'] ? '<strong>configured</strong>' : '<strong>not configured</strong>'; ?><?php echo $user ? ' for ' . esc_html( $user->user_login ) : ''; ?></td></tr>
				<tr><th>Available MCP tools</th><td><?php echo (int) $status['tool_count']; ?> (feature-detected for this site)</td></tr>
				<tr><th>Media upload</th><td>Public URL: <strong><?php echo $status['media']['public_url'] ? 'available' : 'unavailable'; ?></strong> · Device/browser transfer: <strong><?php echo $status['media']['device_browser'] ? 'available' : 'unavailable'; ?></strong> · WPVibe SVG sanitizer: <strong><?php echo $status['media']['svg_sanitizer'] ? 'detected' : 'not detected'; ?></strong></td></tr>
				<tr><th>Last MCP request</th><td><?php echo $status['last_request'] ? esc_html( wp_date( 'Y-m-d H:i:s', $status['last_request'] ) ) : 'Never'; ?></td></tr>
			</tbody></table>

			<h2>Detected capabilities</h2>
			<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Capability</th><th>Status</th></tr></thead><tbody>
			<?php foreach ( $status['capabilities'] as $name => $available ) : if ( 'hosted_only' === $name ) { continue; } ?>
				<tr><td><code><?php echo esc_html( $name ); ?></code></td><td><?php echo $available ? '<span style="color:#008a20">Available</span>' : '<span style="color:#646970">Not detected / unavailable on this site</span>'; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
			<p><strong>Hosted-only / not applicable to a single-site Direct MCP:</strong> <?php echo esc_html( implode( ', ', $status['capabilities']['hosted_only'] ) ); ?>.</p>

			<h2>Access token</h2>
			<p>Preferred: <code>Authorization: Bearer YOUR_TOKEN</code>. Regenerating immediately revokes the previous token.</p>
			<p><strong>Query-string compatibility:</strong> <?php echo $status['query_token_enabled'] ? '<strong>enabled</strong>' : '<strong>disabled by default</strong>'; ?>.</p>
			<?php if ( $status['query_token_enabled'] ) : ?><p><strong>Compatibility URL:</strong> <code><?php echo esc_html( $status['endpoint'] ); ?>?token=YOUR_TOKEN</code></p><?php endif; ?>
			<p><em>Security warning:</em> query-string tokens can enter browser history, proxy logs, analytics, and server logs. Use Bearer authentication whenever the client supports headers, and rotate a URL token if it may have leaked.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px"><input type="hidden" name="action" value="wpvdmcp_generate_token"><?php wp_nonce_field( 'wpvdmcp_action' ); ?><button class="button button-primary"><?php echo $status['authentication_configured'] ? 'Regenerate token' : 'Generate token'; ?></button></form>
			<?php if ( $status['authentication_configured'] ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="wpvdmcp_revoke_token"><?php wp_nonce_field( 'wpvdmcp_action' ); ?><button class="button" onclick="return confirm('Revoke the current MCP token?')">Revoke token</button></form><?php endif; ?>

			<h2>Settings</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="wpvdmcp_save"><?php wp_nonce_field( 'wpvdmcp_action' ); ?><p><label><input type="checkbox" name="enabled" <?php checked( $status['enabled'] ); ?>> Enable direct MCP endpoint</label></p><p><label><input type="checkbox" name="allow_query_token" <?php checked( $status['query_token_enabled'] ); ?>> Allow query-string token compatibility for clients that cannot send authentication headers</label><br><span class="description">Disabled by default because URL credentials can leak through logs, browser history, analytics, referrers, screenshots, and support tooling.</span></p><p><button class="button button-primary">Save settings</button></p></form>

			<h2>Client configuration</h2>
			<pre style="max-width:1100px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:16px">{
  "mcpServers": {
    "wordpress": {
      "url": "<?php echo esc_html( $status['endpoint'] ); ?>",
      "headers": { "Authorization": "Bearer YOUR_TOKEN" }
    }
  }
}</pre>
			<p>Modern MCP requests use the <code>2026-07-28</code> discovery/version metadata. Legacy <code>initialize</code> clients remain supported for compatibility.</p>

			<h2>Recent Direct MCP activity</h2>
			<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Time</th><th>Tool</th><th>Result</th><th>Target</th></tr></thead><tbody>
			<?php if ( ! $log ) : ?><tr><td colspan="4">No direct MCP calls yet.</td></tr><?php else : foreach ( array_slice( $log, 0, 20 ) as $row ) : ?>
			<tr><td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $row['time'] ) ); ?></td><td><code><?php echo esc_html( $row['tool'] ); ?></code></td><td><?php echo ! empty( $row['success'] ) ? '<span style="color:#008a20">Success</span>' : '<span style="color:#b32d2e">Error</span>'; ?> <?php echo ! empty( $row['message'] ) ? esc_html( $row['message'] ) : ''; ?></td><td><code><?php echo esc_html( wp_json_encode( $row['summary'] ) ); ?></code></td></tr>
			<?php endforeach; endif; ?></tbody></table>
		</div>
		<?php
	}

	private function render_approval( $approval_id ) {
		if ( ! $approval_id ) {
			return;
		}
		$approval = WPVDMCP_Approvals::status( $approval_id );
		if ( is_wp_error( $approval ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( $approval->get_error_message() ) . '</p></div>';
			return;
		}
		?>
		<div class="notice notice-warning" style="padding:12px 16px"><h2 style="margin-top:4px">Direct MCP approval</h2><p><strong><?php echo esc_html( $approval['summary'] ); ?></strong></p><p>Status: <code><?php echo esc_html( $approval['status'] ); ?></code> · Operation: <code><?php echo esc_html( $approval['operation'] ); ?></code> · Expires: <?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $approval['expires_at'] ) ); ?></p>
		<?php if ( 'pending' === $approval['status'] ) : ?>
			<p>Approve only if this exactly matches the change you asked the AI to make. Approval is one-time and payload-bound.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px"><input type="hidden" name="action" value="wpvdmcp_approval"><input type="hidden" name="approval_id" value="<?php echo esc_attr( $approval_id ); ?>"><input type="hidden" name="decision" value="approve"><?php wp_nonce_field( 'wpvdmcp_action' ); ?><button class="button button-primary">Approve once</button></form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="wpvdmcp_approval"><input type="hidden" name="approval_id" value="<?php echo esc_attr( $approval_id ); ?>"><input type="hidden" name="decision" value="reject"><?php wp_nonce_field( 'wpvdmcp_action' ); ?><button class="button">Reject</button></form>
		<?php endif; ?></div>
		<?php
	}
}
