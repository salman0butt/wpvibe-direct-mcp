<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Direct-MCP approval bridge for WPVibe's destructive native CLI executor.
 *
 * The public /cli/run route remains WPVibe's classifier. When WPVibe returns
 * approval_required, Direct MCP stores the exact operation/dry-run snapshot in
 * a short-lived local transient and binds it to a one-time browser approval.
 * After approval we invoke WPVibe_CLI::run_approved() locally with that exact
 * snapshot, so the Worker-only /cli/run-approved REST route never becomes
 * model-accessible and WPVibe's own drift checks still run before execution.
 */
final class WPVDMCP_CLI_Safety {
	public static function definitions() {
		if ( ! WPVDMCP_Compatibility::route_exists( '/wpvibe/v1/cli/run' ) ) {
			return array();
		}
		return array(
			array(
				'name' => 'run_wp_cli',
				'description' => 'Run WPVibe native PHP WP-CLI emulation. Read and reversible commands follow WPVibe directly; destructive commands pause for one-time Direct browser approval and then execute through WPVibe_CLI::run_approved() with the exact approved dry-run snapshot. No shell/eval execution is added.',
				'inputSchema' => array(
					'type' => 'object',
					'properties' => (object) array(
						'command' => array( 'type' => 'string', 'description' => 'Allowlisted WP-CLI-style command.' ),
						'confirm_write' => array( 'type' => 'boolean', 'description' => 'Confirm a reversible write when WPVibe requires its second stage.' ),
						'approval_id' => array( 'type' => 'string', 'description' => 'One-time Direct MCP approval ID returned when WPVibe classifies this command as destructive.' ),
					),
					'required' => array( 'command' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public static function handles( $name ) {
		return 'run_wp_cli' === $name;
	}

	public static function execute( $name, $args ) {
		if ( ! self::handles( $name ) ) {
			return new WP_Error( 'unknown_cli_tool', 'Unknown CLI safety tool.', array( 'status' => 404 ) );
		}
		$args = is_array( $args ) ? $args : array();
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
			return self::execute_approved( $approval_id, $command, $confirm );
		}

		$result = WPVDMCP_Tools::execute( 'run_wp_cli', array( 'command' => $command, 'confirm_write' => $confirm ) );
		if ( ! is_wp_error( $result ) || 'approval_required' !== $result->get_error_code() ) {
			return $result;
		}

		$snapshot = self::extract_approval_snapshot( $result );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		$approved_state = array(
			'operation' => $snapshot['operation'],
			'dry_run' => $snapshot['dry_run'],
		);

		if ( WPVDMCP_Approvals::bypass_enabled() ) {
			return self::run_approved_native( $command, $confirm, $approved_state );
		}

		$payload = array(
			'command' => $command,
			'confirm_write' => $confirm,
			'approved_state' => $approved_state,
		);
		$approval = WPVDMCP_Approvals::request( 'run_wp_cli', $payload, $result->get_error_message() );
		if ( is_wp_error( $approval ) ) {
			return $approval;
		}
		set_transient(
			self::snapshot_key( $approval['approval_id'] ),
			array( 'approved_state' => $approved_state ),
			WPVDMCP_Approvals::TTL
		);

		return array_merge( $approval, array(
			'status' => 'approval_required',
			'approval_status' => isset( $approval['status'] ) ? $approval['status'] : 'pending',
			'command' => $snapshot['command'],
			'operation' => $approved_state['operation'],
			'dry_run' => $approved_state['dry_run'],
		) );
	}

	private static function execute_approved( $approval_id, $command, $confirm ) {
		$stored = get_transient( self::snapshot_key( $approval_id ) );
		if ( ! is_array( $stored ) || ! isset( $stored['approved_state'] ) || ! is_array( $stored['approved_state'] ) ) {
			return new WP_Error( 'approval_snapshot_missing', 'The approved WP-CLI snapshot is missing or expired.', array( 'status' => 410 ) );
		}
		$payload = array(
			'command' => $command,
			'confirm_write' => $confirm,
			'approved_state' => $stored['approved_state'],
		);
		$consumed = WPVDMCP_Approvals::consume( $approval_id, 'run_wp_cli', $payload );
		if ( is_wp_error( $consumed ) ) {
			return $consumed;
		}
		delete_transient( self::snapshot_key( $approval_id ) );
		return self::run_approved_native( $command, $confirm, $stored['approved_state'] );
	}

	private static function extract_approval_snapshot( $error ) {
		$data = $error->get_error_data();
		$data = is_array( $data ) ? $data : array();
		$payload = $data;

		// WPVDMCP_Tools::dispatch() wraps REST error bodies under response; core
		// WP REST puts the original WP_Error data one level deeper under data.
		if ( isset( $data['response'] ) && is_array( $data['response'] ) ) {
			$response = $data['response'];
			$payload = isset( $response['data'] ) && is_array( $response['data'] ) ? $response['data'] : $response;
		}
		$operation = isset( $payload['operation'] ) ? (string) $payload['operation'] : '';
		$dry_run = isset( $payload['dry_run'] ) ? $payload['dry_run'] : null;
		if ( '' === $operation || ! is_array( $dry_run ) ) {
			return new WP_Error( 'approval_snapshot_invalid', 'WPVibe requested approval without a usable operation/dry-run snapshot.', array( 'status' => 502 ) );
		}
		return array(
			'operation' => $operation,
			'dry_run' => $dry_run,
			'command' => isset( $payload['command'] ) && is_string( $payload['command'] ) ? $payload['command'] : $operation,
		);
	}

	private static function run_approved_native( $command, $confirm, $approved_state ) {
		if ( ! class_exists( 'WPVibe_CLI' ) || ! method_exists( 'WPVibe_CLI', 'run_approved' ) ) {
			return new WP_Error( 'tool_unavailable', 'The installed WPVibe version does not expose native approved CLI execution.', array( 'status' => 501 ) );
		}
		$cli = new WPVibe_CLI();
		$result = $cli->run_approved(
			$command,
			$confirm,
			wp_json_encode( $approved_state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( $result instanceof WP_REST_Response ) {
			return array( 'status' => $result->get_status(), 'data' => $result->get_data() );
		}
		return array( 'status' => 200, 'data' => $result );
	}

	private static function snapshot_key( $approval_id ) {
		return 'wpvdmcp_cli_snapshot_' . hash( 'sha256', (string) $approval_id );
	}
}
