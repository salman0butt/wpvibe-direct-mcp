<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPVDMCP_Admin {
	private static $instance;
	public static function instance() {
		if ( ! self::$instance ) { self::$instance = new self(); }
		return self::$instance;
	}
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ), 99 );
		add_action( 'admin_post_wpvdmcp_generate_token', array( $this, 'generate_token' ) );
		add_action( 'admin_post_wpvdmcp_revoke_token', array( $this, 'revoke_token' ) );
		add_action( 'admin_post_wpvdmcp_save', array( $this, 'save' ) );
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
			if ( current_user_can( 'activate_plugins' ) && ! is_network_admin() && ! isset( $_GET['activate-multi'] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp' ) );
				exit;
			}
		}
	}
	public function generate_token() {
		$this->guard();
		$token = 'wpvd_' . wp_generate_password( 64, false, false );
		update_option( 'wpvdmcp_token_hash', hash( 'sha256', $token ), false );
		update_option( 'wpvdmcp_user_id', get_current_user_id(), false );
		set_transient( 'wpvdmcp_new_token_' . get_current_user_id(), $token, 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_generated=1' ) );
		exit;
	}
	public function revoke_token() {
		$this->guard();
		delete_option( 'wpvdmcp_token_hash' );
		delete_option( 'wpvdmcp_user_id' );
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&token_revoked=1' ) );
		exit;
	}
	public function save() {
		$this->guard();
		update_option( 'wpvdmcp_enabled', isset( $_POST['enabled'] ) ? '1' : '0', false );
		wp_safe_redirect( admin_url( 'admin.php?page=wpvibe-direct-mcp&settings_updated=1' ) );
		exit;
	}
	private function guard() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'wpvdmcp_action' );
	}
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$token = get_transient( 'wpvdmcp_new_token_' . get_current_user_id() );
		if ( $token ) { delete_transient( 'wpvdmcp_new_token_' . get_current_user_id() ); }
		$configured = (bool) get_option( 'wpvdmcp_token_hash', '' );
		$user_id = (int) get_option( 'wpvdmcp_user_id', 0 );
		$user = $user_id ? get_user_by( 'id', $user_id ) : null;
		$endpoint = rest_url( 'wpvibe-direct/v1/mcp' );
		$enabled = '1' === get_option( 'wpvdmcp_enabled', '1' );
		$last_active = (int) get_option( 'wpvdmcp_last_active', 0 );
		$log = get_option( 'wpvdmcp_activity', array() );
		?>
		<div class="wrap">
			<h1>WPVibe Direct MCP</h1>
			<p style="max-width:900px;font-size:15px">A self-hosted MCP transport for WPVibe. It calls WPVibe’s existing protected REST routes inside WordPress, so WPVibe’s capability checks, draft-theme sandbox, PHP linting, allowlisted WP-CLI commands, Elementor integration, and audit features remain in control.</p>
			<?php if ( ! defined( 'WPVIBE_VERSION' ) && ! class_exists( 'WPVibe_REST' ) ) : ?>
				<div class="notice notice-error"><p><strong>WPVibe is not active.</strong> Install and activate WPVibe before using this bridge.</p></div>
			<?php endif; ?>
			<?php if ( $token ) : ?>
				<div class="notice notice-success"><p><strong>Copy this token now. It will not be shown again.</strong></p><p><input id="wpvd-token" type="text" readonly value="<?php echo esc_attr( $token ); ?>" style="width:100%;max-width:900px;font-family:monospace"><button class="button" type="button" onclick="navigator.clipboard.writeText(document.getElementById('wpvd-token').value)">Copy token</button></p></div>
			<?php endif; ?>
			<table class="widefat striped" style="max-width:1000px;margin-top:20px">
				<tbody>
				<tr><th style="width:220px">Status</th><td><?php echo $enabled ? '<span style="color:#008a20;font-weight:700">Enabled</span>' : '<span style="color:#b32d2e;font-weight:700">Disabled</span>'; ?></td></tr>
				<tr><th>MCP endpoint</th><td><code><?php echo esc_html( $endpoint ); ?></code></td></tr>
				<tr><th>Authentication</th><td>Bearer token <?php echo $configured ? '<strong>configured</strong>' : '<strong>not configured</strong>'; ?><?php echo $user ? ' for ' . esc_html( $user->user_login ) : ''; ?></td></tr>
				<tr><th>WPVibe</th><td><?php echo defined( 'WPVIBE_VERSION' ) ? 'Active, version ' . esc_html( WPVIBE_VERSION ) : 'Not detected'; ?></td></tr>
				<tr><th>Last MCP request</th><td><?php echo $last_active ? esc_html( wp_date( 'Y-m-d H:i:s', $last_active ) ) : 'Never'; ?></td></tr>
				</tbody>
			</table>

			<h2>Access token</h2>
			<p>Use the token as <code>Authorization: Bearer YOUR_TOKEN</code>. Regenerating immediately revokes the previous token.</p>
			<p><strong>ChatGPT No Auth format:</strong> <code><?php echo esc_html( $endpoint ); ?>?token=YOUR_TOKEN</code></p>
			<p><em>Security note:</em> URL tokens may appear in server logs. Regenerate the token immediately if it is exposed.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin-right:8px">
				<input type="hidden" name="action" value="wpvdmcp_generate_token"><?php wp_nonce_field( 'wpvdmcp_action' ); ?>
				<button class="button button-primary"><?php echo $configured ? 'Regenerate token' : 'Generate token'; ?></button>
			</form>
			<?php if ( $configured ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block"><input type="hidden" name="action" value="wpvdmcp_revoke_token"><?php wp_nonce_field( 'wpvdmcp_action' ); ?><button class="button" onclick="return confirm('Revoke the current MCP token?')">Revoke token</button></form><?php endif; ?>

			<h2>Settings</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wpvdmcp_save"><?php wp_nonce_field( 'wpvdmcp_action' ); ?>
				<p><label><input type="checkbox" name="enabled" <?php checked( $enabled ); ?>> Enable direct MCP endpoint</label></p>
				<p><button class="button button-primary">Save settings</button></p>
			</form>

			<h2>Client configuration</h2>
			<pre style="max-width:1000px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:16px">{
  "mcpServers": {
    "wordpress": {
      "url": "<?php echo esc_html( $endpoint ); ?>",
      "headers": {
        "Authorization": "Bearer YOUR_TOKEN"
      }
    }
  }
}</pre>
			<p>The endpoint supports MCP JSON-RPC methods <code>initialize</code>, <code>ping</code>, <code>tools/list</code>, <code>tools/call</code>, <code>prompts/list</code>, <code>prompts/get</code>, and <code>resources/list</code>.</p>

			<h2>Recent direct MCP activity</h2>
			<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Time</th><th>Tool</th><th>Result</th><th>Target</th></tr></thead><tbody>
			<?php if ( ! $log ) : ?><tr><td colspan="4">No direct MCP calls yet.</td></tr><?php else : foreach ( array_slice( $log, 0, 20 ) as $row ) : ?>
			<tr><td><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) $row['time'] ) ); ?></td><td><code><?php echo esc_html( $row['tool'] ); ?></code></td><td><?php echo ! empty( $row['success'] ) ? '<span style="color:#008a20">Success</span>' : '<span style="color:#b32d2e">Error</span>'; ?> <?php echo ! empty( $row['message'] ) ? esc_html( $row['message'] ) : ''; ?></td><td><code><?php echo esc_html( wp_json_encode( $row['summary'] ) ); ?></code></td></tr>
			<?php endforeach; endif; ?></tbody></table>
		</div>
		<?php
	}
}
