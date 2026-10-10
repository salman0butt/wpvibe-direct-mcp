<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Runtime worker-equivalent behavior that must sit in front of the low-level
 * WPVibe route adapter. This class owns public Direct MCP semantics where the
 * hosted WPVibe Worker normally supplies orchestration.
 */
final class WPVDMCP_Runtime_Parity {
	public static function definitions() {
		return array(
			array(
				'name' => 'rest_api',
				'description' => 'Call a registered WordPress REST route as the Direct MCP token owner. GET reads run immediately; POST/PUT/PATCH/DELETE writes require a one-time Direct approval unless the site owner enabled WPVibe dangerous approval bypass. Authentication and worker control-plane routes are blocked.',
				'inputSchema' => self::schema( array(
					'method' => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) ),
					'path' => self::strp( 'REST route beginning with /, for example /wp/v2/pages/123.', true ),
					'params' => self::objp( 'Structured query parameters.' ),
					'body' => array( 'description' => 'JSON body object, or a JSON string for compatibility.', 'oneOf' => array( array( 'type' => 'object' ), array( 'type' => 'string' ) ) ),
					'fields' => self::arrayp( 'Optional selected response fields.', array( 'type' => 'string' ) ),
					'max_response_bytes' => self::intp( 'Maximum encoded response size; Direct MCP still enforces its hard cap.' ),
					'approval_id' => self::strp( 'One-time Direct MCP approval ID returned by the first write call.' ),
				), array( 'method', 'path' ) ),
			),
			array(
				'name' => 'rest_api_write',
				'description' => 'Write-only alias for the hardened Direct MCP WordPress REST bridge. Requires one-time approval unless the site owner enabled WPVibe dangerous approval bypass.',
				'inputSchema' => self::schema( array(
					'method' => array( 'type' => 'string', 'enum' => array( 'POST', 'PUT', 'PATCH', 'DELETE' ) ),
					'path' => self::strp( 'WordPress REST path.', true ),
					'params' => self::objp( 'Optional query parameters.' ),
					'body' => array( 'description' => 'JSON body object, or a JSON string for compatibility.', 'oneOf' => array( array( 'type' => 'object' ), array( 'type' => 'string' ) ) ),
					'fields' => self::arrayp( 'Optional selected response fields.', array( 'type' => 'string' ) ),
					'max_response_bytes' => self::intp( 'Optional response-size cap.' ),
					'approval_id' => self::strp( 'One-time Direct MCP approval ID returned by the first write call.' ),
				), array( 'method', 'path' ) ),
			),
			array(
				'name' => 'run_wp_cli',
				'description' => 'Run WPVibe native PHP WP-CLI emulation. Read/reversible commands follow WPVibe directly; destructive or high-risk commands pause for a Direct browser approval and then execute through WPVibe_CLI::run_approved with the exact approved dry-run snapshot.',
				'inputSchema' => self::schema( array(
					'command' => self::strp( 'Allowlisted WP-CLI-style command.', true ),
					'confirm_write' => array( 'type' => 'boolean', 'description' => 'Confirm reversible writes when WPVibe requires its second stage.' ),
					'approval_id' => self::strp( 'One-time Direct MCP approval ID returned when WPVibe classifies the command as approval-required.' ),
				), array( 'command' ) ),
			),
		);
	}

	public static function handles( $name ) {
		return in_array( $name, array( 'run_ability', 'rest_api', 'rest_api_write', 'run_wp_cli' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		if ( 'run_ability' === $name ) { return self::run_ability( $args ); }
		if ( 'rest_api' === $name ) { return self::rest_api( $args ); }
		if ( 'rest_api_write' === $name ) {
			$method = isset( $args['method'] ) ? strtoupper( sanitize_text_field( $args['method'] ) ) : '';
			if ( ! in_array( $method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
				return new WP_Error( 'invalid_method', 'rest_api_write only accepts POST, PUT, PATCH, or DELETE.', array( 'status' => 400 ) );
			}
			$args['method'] = $method;
			return self::rest_api( $args );
		}
		if ( 'run_wp_cli' === $name ) { return self::run_wp_cli( $args ); }
		return new WP_Error( 'unknown_runtime_tool', 'Unknown runtime parity tool.', array( 'status' => 404, 'tool' => $name ) );
	}

	/** Mirror WordPress core's Abilities REST method contract. */
	private static function run_ability( $args ) {
		$name = isset( $args['name'] ) ? (string) $args['name'] : '';
		if ( ! preg_match( '/^[A-Za-z0-9-]+\/[A-Za-z0-9-]+$/', $name ) ) {
			return new WP_Error( 'invalid_ability_name', 'Ability name must use namespace/ability format.', array( 'status' => 400 ) );
		}
		$info = WPVDMCP_Tools::execute( 'get_ability_info', array( 'name' => $name ) );
		if ( is_wp_error( $info ) ) { return $info; }
		$ability = isset( $info['data'] ) && is_array( $info['data'] ) ? $info['data'] : array();
		$annotations = isset( $ability['meta']['annotations'] ) && is_array( $ability['meta']['annotations'] ) ? $ability['meta']['annotations'] : array();
		$readonly = ! empty( $annotations['readonly'] );
		$destructive = ! empty( $annotations['destructive'] );
		$idempotent = ! empty( $annotations['idempotent'] );
		$method = $readonly ? 'GET' : ( $destructive && $idempotent ? 'DELETE' : 'POST' );
		$input = array_key_exists( 'input', $args ) ? $args['input'] : null;
		$approval_payload = array( 'name' => $name, 'input' => $input, 'method' => $method );
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
		$route = '/wp-abilities/v1/abilities/' . $name . '/run';
		if ( 'GET' === $method || 'DELETE' === $method ) {
			return WPVDMCP_Tools::dispatch( $method, $route, array( 'input' => $input ) );
		}
		return WPVDMCP_Tools::dispatch( 'POST', $route, array(), array( 'input' => $input ) );
	}

	/** Public generic REST bridge with the hosted Worker's approval semantics. */
	private static function rest_api( $args ) {
		$method = isset( $args['method'] ) ? strtoupper( sanitize_text_field( $args['method'] ) ) : 'GET';
		if ( ! in_array( $method, array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			return new WP_Error( 'invalid_method', 'Unsupported REST method.', array( 'status' => 400 ) );
		}
		$path = self::normalize_rest_path( isset( $args['path'] ) ? $args['path'] : '' );
		if ( self::is_blocked_rest_path( $path ) ) {
			return new WP_Error( 'blocked_route', 'This authentication- or worker-sensitive REST route is blocked by the Direct MCP bridge.', array( 'status' => 403, 'path' => $path ) );
		}
		$params = isset( $args['params'] ) && is_array( $args['params'] ) ? $args['params'] : array();
		$body = isset( $args['body'] ) ? $args['body'] : array();
		if ( is_string( $body ) && '' !== trim( $body ) ) {
			$decoded = json_decode( $body, true );
			if ( ! is_array( $decoded ) || JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error( 'invalid_json_body', 'body must be an object or a valid JSON object string.', array( 'status' => 400 ) );
			}
			$body = $decoded;
		}
		if ( ! is_array( $body ) ) { $body = array(); }
		$fields = isset( $args['fields'] ) && is_array( $args['fields'] ) ? array_values( array_filter( array_map( 'sanitize_key', $args['fields'] ) ) ) : array();
		$max = isset( $args['max_response_bytes'] ) ? absint( $args['max_response_bytes'] ) : 262144;
		$max = max( 1024, min( 1048576, $max ) );
		$normalized = array( 'method' => $method, 'path' => $path, 'params' => $params, 'body' => $body, 'fields' => $fields, 'max_response_bytes' => $max );
		if ( 'GET' !== $method && ! WPVDMCP_Approvals::bypass_enabled() ) {
			$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			if ( '' === $approval_id ) {
				$approval = WPVDMCP_Approvals::request( 'rest_api_write', $normalized, $method . ' ' . $path );
				if ( is_wp_error( $approval ) ) { return $approval; }
				return array_merge( $approval, array( 'status' => 'approval_required', 'method' => $method, 'path' => $path ) );
			}
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'rest_api_write', $normalized );
			if ( is_wp_error( $consumed ) ) { return $consumed; }
		}
		return WPVDMCP_Tools::execute( 'rest_api', $normalized );
	}

	/**
	 * Replace the hosted Worker's proof-protected CLI approval handoff locally.
	 * WPVibe itself remains the classifier and executor; Direct only stores the
	 * exact dry-run snapshot while the browser approval is pending.
	 */
	private static function run_wp_cli( $args ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'forbidden', 'WPVibe CLI execution requires manage_options.', array( 'status' => 403 ) );
		}
		$command = isset( $args['command'] ) ? trim( (string) $args['command'] ) : '';
		if ( '' === $command ) {
			return new WP_Error( 'invalid_command', 'command is required.', array( 'status' => 400 ) );
		}
		$confirm = ! empty( $args['confirm_write'] );
		$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';

		if ( '' !== $approval_id ) {
			$snapshot = get_transient( self::cli_snapshot_key( $approval_id ) );
			if ( ! is_array( $snapshot ) || empty( $snapshot['approved_state'] ) ) {
				return new WP_Error( 'approval_snapshot_missing', 'The approved WP-CLI snapshot is missing or expired.', array( 'status' => 410 ) );
			}
			$payload = array( 'command' => $command, 'confirm_write' => $confirm, 'approved_state' => $snapshot['approved_state'] );
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'run_wp_cli', $payload );
			if ( is_wp_error( $consumed ) ) { return $consumed; }
			delete_transient( self::cli_snapshot_key( $approval_id ) );
			return self::run_wp_cli_approved_native( $command, $confirm, $snapshot['approved_state'] );
		}

		$result = WPVDMCP_Tools::execute( 'run_wp_cli', array( 'command' => $command, 'confirm_write' => $confirm ) );
		if ( ! is_wp_error( $result ) || 'approval_required' !== $result->get_error_code() ) { return $result; }
		$data = $result->get_error_data();
		$data = is_array( $data ) ? $data : array();
		$approved_state = array(
			'operation' => isset( $data['operation'] ) ? $data['operation'] : null,
			'dry_run' => isset( $data['dry_run'] ) ? $data['dry_run'] : null,
		);
		if ( null === $approved_state['operation'] || ! is_array( $approved_state['dry_run'] ) ) {
			return new WP_Error( 'approval_snapshot_invalid', 'WPVibe requested approval without a usable operation/dry-run snapshot.', array( 'status' => 502 ) );
		}
		if ( WPVDMCP_Approvals::bypass_enabled() ) {
			return self::run_wp_cli_approved_native( $command, $confirm, $approved_state );
		}
		$payload = array( 'command' => $command, 'confirm_write' => $confirm, 'approved_state' => $approved_state );
		$approval = WPVDMCP_Approvals::request( 'run_wp_cli', $payload, $result->get_error_message() );
		if ( is_wp_error( $approval ) ) { return $approval; }
		set_transient( self::cli_snapshot_key( $approval['approval_id'] ), array( 'approved_state' => $approved_state ), WPVDMCP_Approvals::TTL );
		return array_merge( $approval, array(
			'status' => 'approval_required',
			'command' => isset( $data['command'] ) ? $data['command'] : $command,
			'operation' => $approved_state['operation'],
			'dry_run' => $approved_state['dry_run'],
		) );
	}

	private static function run_wp_cli_approved_native( $command, $confirm, $approved_state ) {
		if ( ! class_exists( 'WPVibe_CLI' ) || ! method_exists( 'WPVibe_CLI', 'run_approved' ) ) {
			return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose native approved CLI execution.', array( 'status' => 501 ) );
		}
		$cli = new WPVibe_CLI();
		$result = $cli->run_approved( $command, $confirm, wp_json_encode( $approved_state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		if ( is_wp_error( $result ) ) { return $result; }
		return array( 'status' => 200, 'data' => $result );
	}

	private static function cli_snapshot_key( $approval_id ) {
		return 'wpvdmcp_cli_snapshot_' . hash( 'sha256', (string) $approval_id );
	}

	private static function normalize_rest_path( $path ) {
		$path = (string) $path;
		$parsed = wp_parse_url( $path, PHP_URL_PATH );
		$path = '/' . ltrim( false !== $parsed && null !== $parsed ? $parsed : $path, '/' );
		if ( 0 === strpos( $path, '/wp-json/' ) ) { $path = substr( $path, 8 ); }
		return '/' . ltrim( $path, '/' );
	}

	private static function is_blocked_rest_path( $path ) {
		$lower = strtolower( (string) $path );
		if ( 0 === strpos( $lower, '/wpvibe-direct/v1/' ) ) { return true; }
		if ( false !== strpos( $lower, '/application-passwords' ) ) { return true; }
		$exact = array(
			'/wpvibe/v1/authorize', '/wpvibe/v1/authorize/preflight', '/wpvibe/v1/connection-status',
			'/wpvibe/v1/connection-check-challenge', '/wpvibe/v1/op-proof/check', '/wpvibe/v1/cli/run-approved',
			'/wpvibe/v1/code-snippet', '/wpvibe/v1/builder-login', '/wpvibe/v1/detached/run',
			'/wpvibe/v1/audit-log/record', '/wpvibe/v1/classic-theme-safety', '/wpvibe/v1/draft-theme/compile-sources',
			'/wpvibe/v1/self-update/health', '/wpvibe/v1/self-update/run'
		);
		if ( in_array( rtrim( $lower, '/' ), $exact, true ) ) { return true; }
		return 0 === strpos( $lower, '/wpvibe/v1/self-update/' );
	}

	private static function schema( $properties, $required = array() ) {
		foreach ( $properties as $key => $property ) {
			if ( ! empty( $property['_required'] ) && ! in_array( $key, $required, true ) ) { $required[] = $key; }
			unset( $property['_required'] );
			$properties[ $key ] = $property;
		}
		$schema = array( 'type' => 'object', 'properties' => (object) $properties, 'additionalProperties' => false );
		if ( $required ) { $schema['required'] = array_values( array_unique( $required ) ); }
		return $schema;
	}
	private static function strp( $description, $required = false ) { return array( 'type' => 'string', 'description' => $description, '_required' => $required ); }
	private static function intp( $description ) { return array( 'type' => 'integer', 'description' => $description ); }
	private static function objp( $description ) { return array( 'type' => 'object', 'description' => $description ); }
	private static function arrayp( $description, $items ) { return array( 'type' => 'array', 'description' => $description, 'items' => $items ); }
}
