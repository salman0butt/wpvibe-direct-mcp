<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPVDMCP_Admin {
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
		$old_hash = get_option( 'wpvdmcp_token_hash', null );
		$old_user = get_option( 'wpvdmcp_user_id', null );
		$token = 'wpvd_' . wp_generate_password( 64, false, false );
		$hash = hash( 'sha256', $token );
		$user_id = get_current_user_id();
		update_option( 'wpvdmcp_token_hash', $hash, false );
		update_option( 'wpvdmcp_user_id', $user_id, false );
		if ( $hash !== get_option( 'wpvdmcp_token_hash', null ) || $user_id !== (int) get_option( 'wpvdmcp_user_id', 0 ) ) {
			$this->restore_token_state( $old_hash, $old_user );
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_error=1' ) );
			exit;
		}
		if ( ! set_transient( 'wpvdmcp_new_token_' . $user_id, $token, 5 * MINUTE_IN_SECONDS ) ) {
			$this->restore_token_state( $old_hash, $old_user );
			wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_error=1' ) );
			exit;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_generated=1' ) );
		exit;
	}

	private function restore_token_state( $hash, $user_id ) {
		if ( null === $hash ) {
			delete_option( 'wpvdmcp_token_hash' );
		} else {
			update_option( 'wpvdmcp_token_hash', $hash, false );
		}
		if ( null === $user_id ) {
			delete_option( 'wpvdmcp_user_id' );
		} else {
			update_option( 'wpvdmcp_user_id', $user_id, false );
		}
	}

	public function revoke_token() {
		$this->guard();
		delete_option( 'wpvdmcp_token_hash' );
		delete_option( 'wpvdmcp_user_id' );
		if ( null !== get_option( 'wpvdmcp_token_hash', null ) || null !== get_option( 'wpvdmcp_user_id', null ) ) {
			update_option( 'wpvdmcp_token_hash', '', false );
			update_option( 'wpvdmcp_user_id', 0, false );
			if ( '' !== get_option( 'wpvdmcp_token_hash', null ) || 0 !== (int) get_option( 'wpvdmcp_user_id', -1 ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_revoke_error=1' ) );
				exit;
			}
		}
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
				<tr><td><code><?php echo esc_html( $name ); ?></code></td><td><?php echo $available ? '<span style="color:#008a20">Available</span>' : '<span style="color:#646970">Not detected / unavailable on this site</span>'; ?> </td></tr>
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
