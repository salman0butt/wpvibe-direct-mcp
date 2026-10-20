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
			$retirement_supported = class_exists( 'WPVibe_Code_Snippet' ) && method_exists( 'WPVibe_Code_Snippet', 'proof_subject' );
			$definition['description'] = 'Create/update WPCode snippets through WPVibe. Eligible dormant writes remain disabled; review-required writes use exact-payload one-time administrator approval. Activation remains human-only in wp-admin.';
			if ( $retirement_supported ) {
				$definition['description'] .= ' This WPVibe version also allows deactivating a WPVibe-created snippet without approval or trashing unchanged WPVibe-created code with one-time administrator approval.';
			} elseif ( isset( $definition['inputSchema']['properties'] ) && is_object( $definition['inputSchema']['properties'] ) ) {
				$definition['inputSchema']['properties']->action['enum'] = array( 'create', 'update' );
			}
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

		$action = isset( $args['action'] ) ? sanitize_key( (string) $args['action'] ) : 'create';
		if ( ! in_array( $action, array( 'create', 'update', 'deactivate', 'delete' ), true ) ) {
			return new WP_Error( 'invalid_snippet_action', 'The snippet action must be create, update, deactivate, or delete.', array( 'status' => 400 ) );
		}
		if ( in_array( $action, array( 'deactivate', 'delete' ), true ) ) {
			if ( ! method_exists( 'WPVibe_Code_Snippet', 'proof_subject' ) ) {
				return new WP_Error( 'snippet_action_unavailable', 'Deactivation and trashing require WPVibe 1.20.4 or a newer upstream code-snippet handler.', array( 'status' => 501 ) );
			}
			if ( ! isset( $args['id'] ) || ! self::valid_retire_id( $args['id'] ) ) {
				return new WP_Error( 'invalid_snippet_id', 'Deactivation and trashing require an existing positive snippet ID.', array( 'status' => 400 ) );
			}
			foreach ( array( 'code', 'title', 'code_type', 'location', 'insert_method' ) as $source_field ) {
				if ( isset( $args[ $source_field ] ) ) {
					return new WP_Error( 'unexpected_snippet_field', 'Retire actions accept only action, id, and an optional approval_id; omit snippet source fields.', array( 'status' => 400 ) );
				}
			}
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
		$action = isset( $args['action'] ) ? sanitize_key( (string) $args['action'] ) : 'create';
		if ( in_array( $action, array( 'deactivate', 'delete' ), true ) ) {
			// Only reached after exact ID validation; never absint() an untrusted retire ID.
			return array( 'action' => $action, 'id' => (int) $args['id'] );
		}
		return array(
			'action' => $action,
			'id' => isset( $args['id'] ) ? absint( $args['id'] ) : 0,
			'code' => isset( $args['code'] ) ? (string) $args['code'] : '',
			'title' => isset( $args['title'] ) ? sanitize_text_field( $args['title'] ) : '',
			'code_type' => isset( $args['code_type'] ) ? sanitize_key( (string) $args['code_type'] ) : '',
			'location' => isset( $args['location'] ) ? sanitize_key( (string) $args['location'] ) : '',
			'insert_method' => isset( $args['insert_method'] ) ? sanitize_key( (string) $args['insert_method'] ) : 'auto',
		);
	}

	/** Accept an integer or its canonical decimal string; never coerce a different target ID. */
	private static function valid_retire_id( $id ) {
		if ( is_int( $id ) ) {
			return $id > 0;
		}
		return is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]*$/D', $id ) && (string) (int) $id === $id;
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
		if ( in_array( $payload['action'], array( 'deactivate', 'delete' ), true ) ) {
			$id = (int) $payload['id'];
			if ( self::still_in_wpcode_cache( $id ) || ( function_exists( 'get_post_status' ) && 'publish' === get_post_status( $id ) ) ) {
				return new WP_Error(
					'snippet_cache_still_active',
					'The upstream retirement operation returned, but the snippet still appears enabled, is present in WPCode\'s execution cache, or the cache could not be verified. It may still run. Ask an administrator to inspect the snippet and WPCode cache in wp-admin; read the state back before any retry.',
					array( 'status' => 500, 'id' => $id, 'may_still_execute' => true )
				);
			}
		}
		if ( $result instanceof WP_REST_Response ) {
			return array( 'status' => $result->get_status(), 'data' => $result->get_data() );
		}
		return array( 'status' => 200, 'data' => $result );
	}

	/** A snippet left in WPCode's execution option may still run after its post is disabled. */
	private static function still_in_wpcode_cache( $id ) {
		return self::cache_contains_or_unknown( get_option( 'wpcode_snippets', array() ), $id, 0 );
	}

	/** Treat unknown shapes as potentially executing, never as evidence of successful deactivation. */
	private static function cache_contains_or_unknown( $value, $id, $depth ) {
		if ( $depth > 12 ) {
			return true;
		}
		if ( is_object( $value ) ) {
			$properties = get_object_vars( $value );
			return empty( $properties ) ? true : self::cache_contains_or_unknown( $properties, $id, $depth + 1 );
		}
		if ( is_array( $value ) ) {
			if ( array_key_exists( 'id', $value ) ) {
				return ! self::valid_retire_id( $value['id'] ) || (int) $value['id'] === $id;
			}
			foreach ( $value as $entry ) {
				if ( self::cache_contains_or_unknown( $entry, $id, $depth + 1 ) ) {
					return true;
				}
			}
			return false;
		}
		if ( self::valid_retire_id( $value ) ) {
			return (int) $value === $id;
		}
		return true;
	}
}
