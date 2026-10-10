<?php
/**
 * Plugin Name: WPVibe Direct MCP
 * Plugin URI:  https://github.com/salman0butt/wpvibe-direct-mcp
 * Description: Adds a self-hosted MCP endpoint to WPVibe, reusing WPVibe's protected REST tools without the hosted WPVibe MCP gateway.
 * Version:     1.3.1
 * Author:      Community build
 * License:     GPL-2.0-or-later
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: vibe-ai
 * Text Domain: wpvibe-direct-mcp
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPVDMCP_VERSION', '1.3.1' );
define( 'WPVDMCP_FILE', __FILE__ );
define( 'WPVDMCP_DIR', plugin_dir_path( __FILE__ ) );

require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-compatibility.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-approvals.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-upload.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-skills.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-parity-fields.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-parity-skills.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-parity-insights.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-parity.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-mcp-app-transport.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-tools.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-server.php';
require_once WPVDMCP_DIR . 'includes/class-wpvdmcp-admin.php';

register_activation_hook( __FILE__, function() {
	if ( false === get_option( 'wpvdmcp_enabled', false ) ) {
		add_option( 'wpvdmcp_enabled', '1', '', false );
	}
	set_transient( 'wpvdmcp_activation_redirect', 1, 60 );
} );

add_action( 'plugins_loaded', function() {
	if ( false !== get_option( 'wpvdmcp_allow_query_token', false ) ) {
		delete_option( 'wpvdmcp_allow_query_token' );
	}
}, 5 );

add_action( 'plugins_loaded', function() {
	WPVDMCP_Parity::bootstrap();
	WPVDMCP_MCP_App_Transport::bootstrap();
	WPVDMCP_Server::instance();
	WPVDMCP_Upload::instance();
	if ( is_admin() ) {
		WPVDMCP_Admin::instance();
	}
}, 30 );
