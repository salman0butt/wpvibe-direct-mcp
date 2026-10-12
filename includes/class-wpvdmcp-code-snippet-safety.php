<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Direct-MCP bridge for WPVibe's WPCode snippet workflow.
 *
 * WPVibe keeps both snippet REST routes behind Worker operation proofs. Direct
 * MCP never mints or exposes those proofs, so it reuses WPVibe_Code_Snippet's
 * in-process handler instead. The upstream dormant classifier runs first. Only
 * writes it refuses with snippet_review_required can enter Direct MCP's
 * payload-bound, one-time browser approval flow. Activation remains WPCode's
 * human wp-admin responsibility.
 */
final class WPVDMCP_Code_Snippet_Safety {
	public static function definitions() {
		if ( ! WPVDMCP_Compatibility::route_exists( '/wpvibe/v1/code-snippet/dormant' ) ) {
			return array();
		}
		foreach ( WPVDMCP_Tools::definitions() as $definition ) {
			if ( 'code_snippet' !== ( $definition['name'] ?? '' ) ) {
				continue;
			}
			$definition['description'] = 'Create or update a WPCode snippet through WPVibe. Qualifying dormant writes stay on WPVibe\'s no-approval disabled path; review-required writes pause for one-time Direct browser approval and then reuse WPVibe\'s approved in-process handler. Activation remains human-only in wp-admin.';
			if ( isset( $definition['inputSchema']['properties'] ) && is_object( $definition['inputSchema']['properties'] ) ) {
				$definition['inputSchema']['properties']->approval_id = array(
					'type' => 'string',
					'description' => 'One-time Direct MCP approval ID returned when WPVibe requires human review for this exact snippet write.',
				);
			}
			return array( $definition );
		}
		return array();
	}

	public static function handles( $name ) {
		return 'code_snippet' === $name;
	}

	public static function execute( $name, $args ) {
		if ( ! self::handles( $name ) ) {
			return new WP_Error( 'unknown_code_snippet_tool', 'Unknown WPCode safety tool.', array( 'status' => 404 ) );
		}
		$args = is_array( $args ) ? $args : array();

		if ( ! class_exists( 'WPCode_Snippet' ) ) {
			return new WP_Error( 'wpcode_missing', 'The WPCode plugin is required for code snippets and is not installed or not active on this site.', array( 'status' => 501 ) );
		}
		if ( ! current_user_can( 'wpcode_edit_snippets' ) ) {
			return new WP_Error( 'missing_capability', 'WPCode snippet writes require wpcode_edit_snippets.', array( 'status' => 403 ) );
		}
		if ( ! class_exists( 'WPVibe_Code_Snippet' ) || ! method_exists( 'WPVibe_Code_Snippet', 'handle' ) ) {
			return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose the native code-snippet handler.', array( 'status' => 501 ) );
		}

		$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
		$payload = self::normalize_payload( $args );

		if ( '' !== $approval_id ) {
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'code_snippet', $payload );
			if ( is_wp_error( $consumed ) ) {
				return $consumed;
			}
			return self::run_native( $payload, false );
		}

		$result = self::run_native( $payload, true );
		if ( ! is_wp_error( $result ) || 'snippet_review_required' !== $result->get_error_code() ) {
			return $result;
		}

		if ( WPVDMCP_Approvals::bypass_enabled() ) {
			return self::run_native( $payload, false );
		}

		$approval = WPVDMCP_Approvals::request( 'code_snippet', $payload, $result->get_error_message() );
		if ( is_wp_error( $approval ) ) {
			return $approval;
		}
		$error_data = $result->get_error_data();
		$reason = is_array( $error_data ) && isset( $error_data['reason'] ) ? (string) $error_data['reason'] : '';
		return array_merge( $approval, array(
			'status' => 'approval_required',
			'approval_status' => isset( $approval['status'] ) ? $approval['status'] : 'pending',
			'reason' => $reason,
		) );
	}

	private static function normalize_payload( $args ) {
		return array(
			'action' => isset( $args['action'] ) && 'update' === sanitize_key( (string) $args['action'] ) ? 'update' : 'create',
			'id' => isset( $args['id'] ) ? absint( $args['id'] ) : 0,
			'code' => isset( $args['code'] ) ? (string) $args['code'] : '',
			'title' => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '',
			'code_type' => isset( $args['code_type'] ) ? sanitize_key( (string) $args['code_type'] ) : '',
			'location' => isset( $args['location'] ) ? sanitize_key( (string) $args['location'] ) : '',
			'insert_method' => isset( $args['insert_method'] ) ? sanitize_key( (string) $args['insert_method'] ) : 'auto',
		);
	}

	private static function run_native( $payload, $dormant ) {
		$route = $dormant ? '/wpvibe/v1/code-snippet/dormant' : '/wpvibe/v1/code-snippet';
		$request = new WP_REST_Request( 'POST', $route );
		foreach ( $payload as $key => $value ) {
			$request->set_param( $key, $value );
		}
		$result = WPVibe_Code_Snippet::handle( $request, (bool) $dormant );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $result instanceof WP_REST_Response ) {
			return array( 'status' => $result->get_status(), 'data' => $result->get_data() );
		}
		return array( 'status' => 200, 'data' => $result );
	}
}
