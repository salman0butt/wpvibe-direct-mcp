<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bounded WAF-safe retry envelope for normal model-facing Direct MCP tools.
 *
 * Managed hosts can reject raw PHP/JS/CSS/SQL in an MCP JSON body before
 * WordPress sees it. This wrapper only transports base64-encoded UTF-8 JSON
 * arguments; after decoding, the exact same public tool handlers execute, so
 * capabilities, approvals, route guards, provider boundaries, and replay
 * protection are identical to an ordinary tools/call.
 */
final class WPVDMCP_Armored_Call {
	const MAX_DECODED_BYTES = 3145728; // 3 MiB.
	const MAX_ENCODED_BYTES = 4194304; // Canonical base64 size for 3 MiB.

	public static function definitions() {
		return array(
			array(
				'name' => 'call_armored',
				'description' => 'WAF-safe retry wrapper for a normal exposed Direct MCP tool. Base64-encode a UTF-8 JSON object containing that tool arguments. The decoded call still uses the normal tool implementation, approvals, capability checks, provider boundaries and safety gates.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => (object) array(
						'tool_name' => array( 'type' => 'string', 'description' => 'Exact name of an exposed model-facing Direct MCP tool.' ),
						'arguments_base64' => array( 'type' => 'string', 'description' => 'Canonical base64 encoding of a UTF-8 JSON object containing the target tool arguments.' ),
					),
					'required' => array( 'tool_name', 'arguments_base64' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public static function handles( $name ) {
		return 'call_armored' === $name;
	}

	public static function execute( $name, $args ) {
		if ( ! self::handles( $name ) ) {
			return new WP_Error( 'unknown_armored_tool', 'Unknown armored-call tool.', array( 'status' => 404 ) );
		}
		$args = is_array( $args ) ? $args : array();
		$target = isset( $args['tool_name'] ) && is_string( $args['tool_name'] ) ? trim( $args['tool_name'] ) : '';
		$encoded = isset( $args['arguments_base64'] ) && is_string( $args['arguments_base64'] ) ? $args['arguments_base64'] : '';
		if ( '' === $target || '' === $encoded ) {
			return new WP_Error( 'invalid_armored_payload', 'tool_name and arguments_base64 are required.', array( 'status' => 400 ) );
		}
		if ( strlen( $encoded ) > self::MAX_ENCODED_BYTES ) {
			return new WP_Error( 'invalid_armored_payload', 'The armored argument payload is too large.', array( 'status' => 413 ) );
		}

		$decoded = base64_decode( $encoded, true );
		if ( false === $decoded || base64_encode( $decoded ) !== $encoded || strlen( $decoded ) > self::MAX_DECODED_BYTES ) {
			return new WP_Error( 'invalid_armored_payload', 'arguments_base64 must be canonical base64 within the Direct MCP size limit.', array( 'status' => 400 ) );
		}
		$decoded_object = json_decode( $decoded );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_object( $decoded_object ) ) {
			return new WP_Error( 'invalid_armored_payload', 'Decoded armored arguments must be a UTF-8 JSON object.', array( 'status' => 400 ) );
		}
		$decoded_args = json_decode( $decoded, true );
		if ( ! is_array( $decoded_args ) ) {
			$decoded_args = array();
		}

		$definition = self::exposed_definition( $target );
		if ( null === $definition ) {
			return new WP_Error( 'unknown_armored_target', 'The armored target is not an exposed Direct MCP tool.', array( 'status' => 404, 'tool' => $target ) );
		}
		if ( 'call_armored' === $target || 'approval_decide' === $target || self::app_only( $definition ) ) {
			return new WP_Error( 'armored_target_forbidden', 'This recursive or app-only helper cannot be called through call_armored.', array( 'status' => 403, 'tool' => $target ) );
		}

		if ( WPVDMCP_Parity::handles( $target ) ) {
			return WPVDMCP_Parity::execute( $target, $decoded_args );
		}
		return WPVDMCP_Tools::execute( $target, $decoded_args );
	}

	private static function exposed_definition( $target ) {
		$definitions = WPVDMCP_Parity::merge_definitions( WPVDMCP_Tools::definitions() );
		foreach ( $definitions as $definition ) {
			if ( isset( $definition['name'] ) && $target === $definition['name'] ) {
				return $definition;
			}
		}
		return null;
	}

	private static function app_only( $tool ) {
		$visibility = isset( $tool['_meta']['ui']['visibility'] ) && is_array( $tool['_meta']['ui']['visibility'] ) ? $tool['_meta']['ui']['visibility'] : array();
		return $visibility && in_array( 'app', $visibility, true ) && ! in_array( 'model', $visibility, true );
	}
}
