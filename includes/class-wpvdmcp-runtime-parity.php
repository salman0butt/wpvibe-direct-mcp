<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Runtime worker-equivalent behavior that must sit in front of the low-level
 * WPVibe route adapter. This class owns public Direct MCP semantics where the
 * hosted WPVibe Worker normally supplies orchestration.
 */
final class WPVDMCP_Runtime_Parity {
	public static function definitions() {
		return array();
	}

	public static function handles( $name ) {
		return in_array( $name, array( 'run_ability' ), true );
	}

	public static function execute( $name, $args ) {
		$args = is_array( $args ) ? $args : array();
		if ( 'run_ability' === $name ) {
			return self::run_ability( $args );
		}
		return new WP_Error( 'unknown_runtime_tool', 'Unknown runtime parity tool.', array( 'status' => 404, 'tool' => $name ) );
	}

	/**
	 * Mirror WordPress core's Abilities REST method contract.
	 *
	 * Read-only abilities run with GET. DELETE is reserved for destructive
	 * abilities that are also idempotent. A destructive non-idempotent action
	 * is still POST; treating every destructive ability as DELETE rejects valid
	 * plugin abilities (including future Elementor/plugin operations).
	 */
	private static function run_ability( $args ) {
		$name = isset( $args['name'] ) ? (string) $args['name'] : '';
		if ( ! preg_match( '/^[A-Za-z0-9-]+\/[A-Za-z0-9-]+$/', $name ) ) {
			return new WP_Error( 'invalid_ability_name', 'Ability name must use namespace/ability format.', array( 'status' => 400 ) );
		}

		$info = WPVDMCP_Tools::execute( 'get_ability_info', array( 'name' => $name ) );
		if ( is_wp_error( $info ) ) {
			return $info;
		}
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
				return array_merge( $approval, array(
					'status' => 'approval_required',
					'approval_status' => isset( $approval['status'] ) ? $approval['status'] : 'pending',
					'ability' => $name,
					'annotations' => $annotations,
				) );
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
}
