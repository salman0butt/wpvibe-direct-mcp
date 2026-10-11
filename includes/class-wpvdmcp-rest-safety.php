<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Safety/parity wrapper for the public generic REST tools.
 *
 * Read requests continue to use the existing WPVDMCP_Tools dispatcher directly.
 * Mutations are bound to a one-time Direct MCP approval unless the site owner has
 * enabled WPVibe's Dangerously bypass approvals option. After approval this class
 * delegates to WPVDMCP_Tools so capability checks, credential-route blocking and
 * response-size limits stay centralized in the existing REST bridge.
 */
final class WPVDMCP_REST_Safety {
	public static function definitions() {
		$common = array(
			'method' => array( 'type' => 'string', 'enum' => array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ), 'description' => 'HTTP method.' ),
			'path' => self::strp( 'WordPress REST route beginning with /, for example /wp/v2/pages/123.' ),
			'params' => self::objp( 'Structured query parameters.' ),
			'body' => array( 'description' => 'JSON body object, or a JSON string for compatibility.', 'oneOf' => array( array( 'type' => 'object' ), array( 'type' => 'string' ) ) ),
			'fields' => self::arrayp( 'Optional selected response fields. Also forwarded as WordPress _fields on GET.', array( 'type' => 'string' ) ),
			'max_response_bytes' => self::intp( 'Maximum encoded response size. Default 262144, hard cap 1048576 bytes.' ),
			'approval_id' => self::strp( 'One-time Direct MCP approval ID returned by an earlier write call.' ),
		);
		$write = $common;
		$write['method']['enum'] = array( 'POST', 'PUT', 'PATCH', 'DELETE' );
		return array(
			array(
				'name' => 'rest_api',
				'description' => 'Call a registered WordPress REST API route internally as the token owner. GET runs immediately; writes require one-time approval unless the site owner enabled WPVibe Dangerously bypass approvals. Destination capability checks, credential-route blocking and bounded responses remain enforced.',
				'inputSchema' => self::schema( $common, array( 'method', 'path' ) ),
			),
			array(
				'name' => 'rest_api_write',
				'description' => 'Perform an approval-gated WordPress REST mutation through the same hardened Direct MCP REST bridge. DELETE never forwards force=true, so trashable WordPress content is not silently hard-deleted.',
				'inputSchema' => self::schema( $write, array( 'method', 'path' ) ),
			),
		);
	}

	public static function handles( $name ) {
		return in_array( $name, array( 'rest_api', 'rest_api_write' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		$method = isset( $args['method'] ) ? strtoupper( sanitize_text_field( $args['method'] ) ) : 'GET';
		$allowed = 'rest_api_write' === $name ? array( 'POST', 'PUT', 'PATCH', 'DELETE' ) : array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' );
		if ( ! in_array( $method, $allowed, true ) ) {
			return new WP_Error( 'invalid_method', 'rest_api_write' === $name ? 'rest_api_write only accepts POST, PUT, PATCH, or DELETE.' : 'Unsupported REST method.', array( 'status' => 400 ) );
		}

		$path = self::normalize_path( isset( $args['path'] ) ? (string) $args['path'] : '' );
		if ( '' === $path ) {
			return new WP_Error( 'invalid_path', 'A WordPress REST path is required.', array( 'status' => 400 ) );
		}
		if ( self::is_blocked_path( $path ) ) {
			return new WP_Error( 'blocked_route', 'This authentication-sensitive REST route is blocked by the direct MCP bridge.', array( 'status' => 403 ) );
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
		if ( ! is_array( $body ) ) {
			$body = array();
		}

		if ( 'DELETE' === $method ) {
			// Match WPVibe's public REST safety contract: never let generic REST
			// turn a trash-capable delete into an irreversible force delete.
			unset( $params['force'], $body['force'] );
		}

		$delegate = $args;
		$delegate['method'] = $method;
		$delegate['path'] = $path;
		$delegate['params'] = $params;
		$delegate['body'] = $body;
		unset( $delegate['approval_id'] );

		if ( 'GET' === $method ) {
			return WPVDMCP_Tools::execute( 'rest_api', $delegate );
		}

		$approval_payload = array(
			'method' => $method,
			'path' => $path,
			'params' => $params,
			'body' => $body,
		);
		if ( ! WPVDMCP_Approvals::bypass_enabled() ) {
			$approval_id = isset( $args['approval_id'] ) ? (string) $args['approval_id'] : '';
			if ( '' === $approval_id ) {
				$approval = WPVDMCP_Approvals::request( 'rest_api_write', $approval_payload, $method . ' ' . $path );
				if ( is_wp_error( $approval ) ) { return $approval; }
				return array_merge( $approval, array( 'status' => 'approval_required', 'method' => $method, 'path' => $path ) );
			}
			$consumed = WPVDMCP_Approvals::consume( $approval_id, 'rest_api_write', $approval_payload );
			if ( is_wp_error( $consumed ) ) { return $consumed; }
		}

		return WPVDMCP_Tools::execute( 'rest_api', $delegate );
	}

	private static function normalize_path( $path ) {
		$path = trim( (string) $path );
		if ( '' === $path ) { return ''; }
		$parsed = wp_parse_url( $path, PHP_URL_PATH );
		$path = '/' . ltrim( false !== $parsed && null !== $parsed ? $parsed : $path, '/' );
		if ( 0 === strpos( $path, '/wp-json/' ) ) {
			$path = substr( $path, 8 );
		}
		return '/' . ltrim( $path, '/' );
	}

	private static function is_blocked_path( $path ) {
		$lower = strtolower( $path );
		return 0 === strpos( $lower, '/wpvibe-direct/v1/' ) || false !== strpos( $lower, '/application-passwords' );
	}

	private static function schema( $props, $required = array() ) {
		$schema = array( 'type' => 'object', 'properties' => (object) $props, 'additionalProperties' => false );
		if ( $required ) { $schema['required'] = array_values( $required ); }
		return $schema;
	}
	private static function strp( $description ) { return array( 'type' => 'string', 'description' => $description ); }
	private static function intp( $description ) { return array( 'type' => 'integer', 'description' => $description ); }
	private static function objp( $description ) { return array( 'type' => 'object', 'description' => $description ); }
	private static function arrayp( $description, $items ) { return array( 'type' => 'array', 'description' => $description, 'items' => $items ); }
}
