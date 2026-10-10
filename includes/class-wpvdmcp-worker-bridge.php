<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Site-local replacements for WPVibe hosted Worker hops that are protected by
 * per-operation proof credentials. Direct MCP never mints or exposes those
 * credentials; it invokes the installed plugin's safe executor locally after
 * reproducing the same capability/safety boundary.
 */
final class WPVDMCP_Worker_Bridge {
	public static function definitions() { return array(); }

	public static function handles( $name ) {
		return 'code_snippet' === $name;
	}

	public static function execute( $name, $args ) {
		if ( 'code_snippet' === $name ) { return self::code_snippet( is_array( $args ) ? $args : array() ); }
		return new WP_Error( 'unknown_worker_bridge_tool', 'Unknown Direct worker bridge tool.', array( 'status' => 404 ) );
	}

	private static function code_snippet( $args ) {
		if ( ! class_exists( 'WPCode_Snippet' ) ) {
			return new WP_Error( 'wpcode_missing', 'The WPCode plugin is required for code snippets and is not active on this site.', array( 'status' => 501 ) );
		}
		if ( ! current_user_can( 'wpcode_edit_snippets' ) ) {
			return new WP_Error( 'wpvibe_missing_capability', 'This action requires the wpcode_edit_snippets capability.', array( 'status' => 403, 'capability' => 'wpcode_edit_snippets' ) );
		}
		if ( ! class_exists( 'WPVibe_Code_Snippet' ) || ! method_exists( 'WPVibe_Code_Snippet', 'handle' ) ) {
			return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose its native dormant WPCode handler.', array( 'status' => 501 ) );
		}

		$allowed = array( 'action', 'id', 'code', 'title', 'code_type', 'location', 'insert_method' );
		$body = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $args ) ) { $body[ $key ] = $args[ $key ]; }
		}
		// Deliberately never forward an `active` field. WPVibe's dormant path
		// enforces disabled state; human activation remains in wp-admin.
		$request = new WP_REST_Request( 'POST', '/wpvibe/v1/code-snippet/dormant' );
		$request->set_body_params( $body );
		$request->set_header( 'content-type', 'application/json' );
		$result = WPVibe_Code_Snippet::handle( $request, true );
		if ( is_wp_error( $result ) ) { return $result; }
		if ( $result instanceof WP_REST_Response ) {
			return array( 'status' => $result->get_status(), 'data' => $result->get_data() );
		}
		return array( 'status' => 200, 'data' => $result );
	}
}
