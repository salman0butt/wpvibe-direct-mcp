<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * WAF-safe retry envelope for code-bearing tool arguments.
 *
 * Some managed hosts reject raw PHP/JS/CSS/SQL before WordPress receives the
 * MCP request. This tool carries only an exposed tool name plus base64 UTF-8
 * JSON arguments; after decoding it uses the exact same public tool handlers,
 * approvals and capability checks as an ordinary tools/call.
 */
final class WPVDMCP_Armored_Call {
	const MAX_DECODED_BYTES = 3145728; // 3 MiB; bounded while allowing page/theme source payloads.

	public static function definitions() {
		return array(
			array(
				'name' => 'call_armored',
				'description' => 'WAF-safe retry wrapper for a normal exposed Direct MCP tool. Base64-encode a UTF-8 JSON object containing that tool arguments. The decoded call still uses the normal tool implementation, approvals, capability checks and safety gates.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => (object) array(
						'tool_name' => array( 'type' => 'string', 'description' => 'Name of an exposed model-facing tool.' ),
						'arguments_base64' => array( 'type' => 'string', 'description' => 'Strict base64 encoding of a UTF-8 JSON object containing the target tool arguments.' ),
					),
					'required' => array( 'tool_name', 'arguments_base64' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public static function handles( $name ) { return 'call_armored' === $name; }

	public static function execute( $name, $args ) {
		if ( 'call_armored' !== $name ) {
			return new WP_Error( 'unknown_armored_tool', 'Unknown armored-call tool.', array( 'status' => 404 ) );
		}
		$args = is_array( $args ) ? $args : array();
		$target = isset( $args['tool_name'] ) ? (string) $args['tool_name'] : '';
		$encoded = isset( $args['arguments_base64'] ) ? (string) $args['arguments_base64'] : '';
		if ( '' === $target || '' === $encoded ) {
			return new WP_Error( 'invalid_armored_payload', 'tool_name and arguments_base64 are required.', array( 'status' => 400 ) );
		}
		if ( strlen( $encoded ) > ( self::MAX_DECODED_BYTES * 2 ) ) {
			return new WP_Error( 'invalid_armored_payload', 'The armored argument payload is too large.', array( 'status' => 413 ) );
		}
		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded || strlen( $decoded ) > self::MAX_DECODED_BYTES ) {
			return new WP_Error( 'invalid_armored_payload', 'arguments_base64 must be strict base64 within the Direct MCP size limit.', array( 'status' => 400 ) );
		}
		$object = json_decode( $decoded );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $object ) ) {
			return new WP_Error( 'invalid_armored_payload', 'Decoded armored arguments must be a UTF-8 JSON object.', array( 'status' => 400 ) );
		}
		$decoded_args = json_decode( $decoded, true );
		if ( ! is_array( $decoded_args ) ) { $decoded_args = array(); }

		$definitions = WPVDMCP_Parity::merge_definitions( WPVDMCP_Tools::definitions() );
		$tool = null;
		foreach ( $definitions as $definition ) {
			if ( isset( $definition['name'] ) && $definition['name'] === $target ) { $tool = $definition; break; }
		}
		if ( null === $tool ) {
			return new WP_Error( 'unknown_armored_target', 'The armored target is not an exposed Direct MCP tool.', array( 'status' => 404, 'tool' => $target ) );
		}
		if ( 'call_armored' === $target || 'approval_decide' === $target || self::app_only( $tool ) ) {
			return new WP_Error( 'armored_target_forbidden', 'This helper/internal UI tool cannot be called through call_armored.', array( 'status' => 403, 'tool' => $target ) );
		}

		if ( WPVDMCP_Parity::handles( $target ) ) { return WPVDMCP_Parity::execute( $target, $decoded_args ); }
		return WPVDMCP_Tools::execute( $target, $decoded_args );
	}

	private static function app_only( $tool ) {
		$visibility = isset( $tool['_meta']['ui']['visibility'] ) && is_array( $tool['_meta']['ui']['visibility'] ) ? $tool['_meta']['ui']['visibility'] : array();
		return $visibility && in_array( 'app', $visibility, true ) && ! in_array( 'model', $visibility, true );
	}
}
