<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Public-contract compatibility for WordPress Abilities and approval status tools. */
final class WPVDMCP_Abilities_Compat {
	public static function definitions() {
		$tools = array(
			array(
				'name' => 'show_approval_panel',
				'description' => 'Open or inspect a pending Direct MCP approval. Accepts WPVibe op_id or Direct MCP approval_id; MCP Apps clients retain the inline panel and every client retains the secure browser fallback.',
				'inputSchema' => self::id_schema(),
			),
			array(
				'name' => 'check_approval_status',
				'description' => 'Check a Direct MCP approval by WPVibe op_id or Direct MCP approval_id. Fleet job ids (fj_...) delegate to the configured hosted fleet provider instead of fabricating cloud state.',
				'inputSchema' => self::id_schema(),
			),
		);

		if ( ! self::abilities_available() ) { return $tools; }
		$tools[] = array(
			'name' => 'discover_abilities',
			'description' => 'Discover REST-exposed WordPress Abilities with schemas and annotations.',
			'inputSchema' => self::schema( array(
				'page' => array( 'type' => 'integer', 'description' => 'Results page.' ),
				'per_page' => array( 'type' => 'integer', 'description' => 'Direct MCP results-per-page extension.' ),
				'category' => array( 'type' => 'string', 'description' => 'Optional ability category.' ),
			) ),
		);
		$tools[] = array(
			'name' => 'get_ability_info',
			'description' => 'Get full schema, permissions metadata, and annotations for one WordPress Ability. Accepts WPVibe ability_name or Direct MCP name.',
			'inputSchema' => self::ability_name_schema(),
		);
		$run_props = array(
			'name' => array( 'type' => 'string', 'description' => 'Direct MCP ability name in namespace/ability format.' ),
			'ability_name' => array( 'type' => 'string', 'description' => 'WPVibe-compatible full ability name in namespace/ability format.' ),
			'input' => array( 'type' => 'object', 'description' => 'Preferred structured ability input object.' ),
			'input_json' => array( 'type' => 'string', 'description' => 'Legacy WPVibe JSON-encoded ability input. Ignored when input is present.' ),
			'readonly' => array( 'type' => 'boolean', 'description' => 'Fallback annotation hint used only if ability metadata cannot be fetched.' ),
			'destructive' => array( 'type' => 'boolean', 'description' => 'Fallback destructive hint used only if ability metadata cannot be fetched.' ),
			'idempotent' => array( 'type' => 'boolean', 'description' => 'Fallback idempotent hint used only if ability metadata cannot be fetched.' ),
			'approval_id' => array( 'type' => 'string', 'description' => 'One-time Direct MCP approval ID for a previously approved local write.' ),
		);
		$run_schema = self::schema( $run_props );
		$run_schema['anyOf'] = array( array( 'required' => array( 'name' ) ), array( 'required' => array( 'ability_name' ) ) );
		$tools[] = array(
			'name' => 'run_ability',
			'description' => 'Run a WordPress Ability. Server metadata is authoritative: readonly => GET, destructive+idempotent => DELETE, otherwise POST. WPVibe annotation flags are fallback hints only when metadata fetch fails. Direct MCP keeps local write/destructive approvals one-time and payload-bound.',
			'inputSchema' => $run_schema,
		);
		return $tools;
	}

	public static function handles( $name ) {
		if ( in_array( $name, array( 'show_approval_panel', 'check_approval_status' ), true ) ) { return true; }
		return self::abilities_available() && in_array( $name, array( 'discover_abilities', 'get_ability_info', 'run_ability' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		if ( 'show_approval_panel' === $name ) {
			$id = self::operation_id( $args );
			if ( '' === $id ) { return new WP_Error( 'invalid_approval_id', 'op_id or approval_id is required.', array( 'status' => 400 ) ); }
			return WPVDMCP_MCP_Apps::execute( 'show_approval_panel', array( 'approval_id' => $id ) );
		}
		if ( 'check_approval_status' === $name ) {
			$id = self::operation_id( $args );
			if ( '' === $id ) { return new WP_Error( 'invalid_approval_id', 'op_id or approval_id is required.', array( 'status' => 400 ) ); }
			if ( 0 === strpos( $id, 'fj_' ) ) {
				return WPVDMCP_Reference_Parity::execute( 'show_fleet_dashboard', array( 'job_id' => $id ) );
			}
			return WPVDMCP_Approvals::status( $id );
		}
		if ( 'discover_abilities' === $name ) {
			return WPVDMCP_Tools::execute( 'discover_abilities', $args );
		}
		if ( 'get_ability_info' === $name ) {
			$name_value = self::ability_name( $args );
			if ( '' === $name_value ) { return new WP_Error( 'invalid_ability_name', 'name or ability_name must use namespace/ability format.', array( 'status' => 400 ) ); }
			return WPVDMCP_Tools::execute( 'get_ability_info', array( 'name' => $name_value ) );
		}
		if ( 'run_ability' === $name ) { return self::run_ability( $args ); }
		return new WP_Error( 'unknown_abilities_compat_tool', 'Unknown Abilities compatibility tool.', array( 'status' => 404 ) );
	}

	private static function run_ability( $args ) {
		$name = self::ability_name( $args );
		if ( '' === $name ) { return new WP_Error( 'invalid_ability_name', 'name or ability_name must use namespace/ability format.', array( 'status' => 400 ) ); }
		$input = null;
		if ( array_key_exists( 'input', $args ) ) {
			$input = $args['input'];
		} elseif ( isset( $args['input_json'] ) && '' !== trim( (string) $args['input_json'] ) ) {
			$input = json_decode( (string) $args['input_json'], true );
			if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $input ) ) {
				return new WP_Error( 'invalid_ability_input_json', 'input_json must be a valid JSON object.', array( 'status' => 400 ) );
			}
		}

		$info = WPVDMCP_Tools::execute( 'get_ability_info', array( 'name' => $name ) );
		$annotations = array();
		if ( ! is_wp_error( $info ) ) {
			$ability = isset( $info['data'] ) && is_array( $info['data'] ) ? $info['data'] : array();
			$annotations = isset( $ability['meta']['annotations'] ) && is_array( $ability['meta']['annotations'] ) ? $ability['meta']['annotations'] : array();
		} else {
			$has_hint = array_key_exists( 'readonly', $args ) || array_key_exists( 'destructive', $args ) || array_key_exists( 'idempotent', $args );
			if ( ! $has_hint ) { return $info; }
			$annotations = array(
				'readonly' => ! empty( $args['readonly'] ),
				'destructive' => ! empty( $args['destructive'] ),
				'idempotent' => ! empty( $args['idempotent'] ),
			);
		}

		$readonly = ! empty( $annotations['readonly'] );
		$destructive = ! empty( $annotations['destructive'] );
		$idempotent = ! empty( $annotations['idempotent'] );
		$method = $readonly ? 'GET' : ( $destructive && $idempotent ? 'DELETE' : 'POST' );
		$approval_payload = array( 'name' => $name, 'input' => $input, 'method' => $method );
		$route = '/wp-abilities/v1/abilities/' . $name . '/run';

		if ( 'elementor/build-composition' === $name && is_array( $input ) && empty( $input['dry_run'] ) ) {
			$preflight = self::preflight_elementor_build_composition( $route, $input );
			if ( is_wp_error( $preflight ) ) { return $preflight; }
		}

		// Direct MCP deliberately remains stricter than the hosted wrapper: every
		// non-readonly local ability mutation is approved, not only destructive ones.
		if ( ! $readonly && ! WPVDMCP_Approvals::bypass_enabled() ) {
			$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			if ( '' === $approval_id ) {
				$approval = WPVDMCP_Approvals::request( 'run_ability', $approval_payload, sprintf( '%s ability %s', $method, $name ) );
				if ( is_wp_error( $approval ) ) { return $approval; }
				return array_merge( $approval, array( 'status' => 'approval_required', 'approval_status' => isset( $approval['status'] ) ? $approval['status'] : 'pending', 'ability' => $name, 'annotations' => $annotations ) );
			}
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'run_ability', $approval_payload );
			if ( is_wp_error( $consumed ) ) { return $consumed; }
		}

		if ( 'GET' === $method || 'DELETE' === $method ) {
			return WPVDMCP_Tools::dispatch( $method, $route, array( 'input' => $input ) );
		}
		$result = self::dispatch_json_post( $route, array( 'input' => $input ) );
		return self::normalize_elementor_mutation_result( $name, $result );
	}

	/**
	 * Compile an Elementor composition without saving it before approval/write.
	 *
	 * Elementor intentionally treats unsupported props/CSS as non-fatal warnings
	 * and still returns success. Direct MCP is stricter for page-building: a
	 * composition with warnings must be corrected before any partial design is
	 * persisted, otherwise the model can report success while requested content
	 * or styles were silently dropped.
	 */
	private static function preflight_elementor_build_composition( $route, $input ) {
		$dry_input = $input;
		$dry_input['dry_run'] = true;
		$preflight = self::dispatch_json_post( $route, array( 'input' => $dry_input ) );
		if ( is_wp_error( $preflight ) ) { return $preflight; }

		$data = isset( $preflight['data'] ) && is_array( $preflight['data'] ) ? $preflight['data'] : array();
		$warnings = isset( $data['warnings'] ) && is_array( $data['warnings'] ) ? array_values( $data['warnings'] ) : array();
		if ( $warnings ) {
			return new WP_Error(
				'elementor_composition_preflight_failed',
				'Elementor composition validation returned warnings. Fix the widget settings/styles before saving so requested page content is not silently dropped.',
				array(
					'status' => 422,
					'post_id' => isset( $data['post_id'] ) ? $data['post_id'] : ( isset( $input['post_id'] ) ? $input['post_id'] : null ),
					'warnings' => $warnings,
				)
			);
		}

		return true;
	}

	/** Surface Elementor's partial-success mutation contracts as actionable MCP errors. */
	private static function normalize_elementor_mutation_result( $name, $result ) {
		if ( is_wp_error( $result ) || ! is_array( $result ) ) { return $result; }
		$data = isset( $result['data'] ) && is_array( $result['data'] ) ? $result['data'] : array();

		if ( 'elementor/build-composition' === $name ) {
			$warnings = isset( $data['warnings'] ) && is_array( $data['warnings'] ) ? array_values( $data['warnings'] ) : array();
			if ( $warnings ) {
				return new WP_Error(
					'elementor_composition_write_warning',
					'Elementor returned warnings after saving the composition. Re-read the page structure and repair the affected content before reporting success.',
					array( 'status' => 409, 'partial_applied' => true, 'response' => $data, 'warnings' => $warnings )
				);
			}
			return $result;
		}

		if ( 'elementor/manage-elements' !== $name ) { return $result; }

		$status = isset( $data['status'] ) ? (string) $data['status'] : '';
		$rows = isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : array();
		$warnings = array();
		$applied = false;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) { continue; }
			if ( 'ok' === ( $row['status'] ?? '' ) ) { $applied = true; }
			if ( ! empty( $row['warnings'] ) && is_array( $row['warnings'] ) ) {
				$warnings = array_merge( $warnings, $row['warnings'] );
			}
		}

		if ( 'partial_error' === $status || 'error' === $status ) {
			return new WP_Error(
				'elementor_manage_elements_partial_failure',
				'Elementor did not apply every requested element operation. Re-read the current page structure and repair only the failed operations before reporting success.',
				array( 'status' => 409, 'partial_applied' => $applied, 'response' => $data )
			);
		}
		if ( $warnings ) {
			return new WP_Error(
				'elementor_manage_elements_warning',
				'Elementor applied the element mutation with warnings. Re-read the current page structure and verify the affected content/styles before reporting success.',
				array( 'status' => 409, 'partial_applied' => $applied, 'response' => $data, 'warnings' => array_values( $warnings ) )
			);
		}

		return $result;
	}

	/**
	 * Dispatch an Abilities mutation exactly like WordPress core's REST tests.
	 *
	 * WordPress 6.9+ parses the top-level `input` from the raw JSON request body.
	 * Populating form/body params or a synthetic JSON-param bag is insufficient
	 * for the real WP_REST_Abilities_V1_Run_Controller. Core sets the JSON content
	 * type and then calls set_body( wp_json_encode( ... ) ).
	 */
	private static function dispatch_json_post( $route, $body ) {
		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		$response = rest_do_request( $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$response = rest_ensure_response( $response );
		$data = $response->get_data();
		$status = $response->get_status();
		if ( $status >= 400 ) {
			$message = is_array( $data ) && isset( $data['message'] ) ? $data['message'] : 'WordPress REST request failed.';
			$code = is_array( $data ) && isset( $data['code'] ) ? $data['code'] : 'rest_error';
			return new WP_Error( $code, $message, array( 'status' => $status, 'response' => $data ) );
		}
		return array( 'status' => $status, 'data' => $data );
	}

	private static function abilities_available() {
		$version = WPVDMCP_Compatibility::wordpress_version();
		return WPVDMCP_Compatibility::route_exists( '/wp-abilities/v1/abilities' ) && ( class_exists( 'WP_Ability' ) || ( $version && version_compare( $version, '6.9', '>=' ) ) );
	}

	private static function ability_name( $args ) {
		$name = isset( $args['name'] ) && '' !== trim( (string) $args['name'] ) ? (string) $args['name'] : ( isset( $args['ability_name'] ) ? (string) $args['ability_name'] : '' );
		return preg_match( '/^[A-Za-z0-9-]+\/[A-Za-z0-9-]+$/', $name ) ? $name : '';
	}

	private static function operation_id( $args ) {
		if ( isset( $args['approval_id'] ) && '' !== trim( (string) $args['approval_id'] ) ) { return (string) $args['approval_id']; }
		return isset( $args['op_id'] ) ? trim( (string) $args['op_id'] ) : '';
	}

	private static function ability_name_schema() {
		$schema = self::schema( array(
			'name' => array( 'type' => 'string', 'description' => 'Direct MCP ability name.' ),
			'ability_name' => array( 'type' => 'string', 'description' => 'WPVibe-compatible full ability name.' ),
		) );
		$schema['anyOf'] = array( array( 'required' => array( 'name' ) ), array( 'required' => array( 'ability_name' ) ) );
		return $schema;
	}

	private static function id_schema() {
		$schema = self::schema( array(
			'approval_id' => array( 'type' => 'string', 'description' => 'Direct MCP approval ID.' ),
			'op_id' => array( 'type' => 'string', 'description' => 'WPVibe-compatible operation or fleet job ID.' ),
		) );
		$schema['anyOf'] = array( array( 'required' => array( 'approval_id' ) ), array( 'required' => array( 'op_id' ) ) );
		return $schema;
	}

	private static function schema( $props ) {
		return array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
	}
}
