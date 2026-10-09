<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Browser-admin approval receipts for Direct MCP write/destructive operations. */
final class WPVDMCP_Approvals {
	const TTL = 1800;

	public static function request( $operation, $payload, $summary = '' ) {
		$owner = (int) get_option( 'wpvdmcp_user_id', 0 );
		if ( ! $owner ) {
			return new WP_Error( 'approval_owner_missing', 'Direct MCP has no configured token owner.', array( 'status' => 401 ) );
		}
		$id = 'ap_' . bin2hex( random_bytes( 16 ) );
		$record = array(
			'id' => $id,
			'owner_user_id' => $owner,
			'operation' => sanitize_key( $operation ),
			'payload_hash' => self::payload_hash( $payload ),
			'summary' => self::truncate( sanitize_text_field( $summary ), 500 ),
			'status' => 'pending',
			'created_at' => time(),
			'expires_at' => time() + self::TTL,
		);
		set_transient( self::key( $id ), $record, self::TTL );
		return self::safe_record( $record );
	}

	public static function approve( $id, $user_id ) {
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$user_id = (int) $user_id;
		$user = get_user_by( 'id', $user_id );
		if ( $user_id !== (int) $record['owner_user_id'] || ! $user || ! user_can( $user, 'manage_options' ) ) {
			return new WP_Error( 'approval_forbidden', 'Only the Direct MCP token owner can approve this operation.', array( 'status' => 403 ) );
		}
		if ( 'pending' !== $record['status'] ) {
			return new WP_Error( 'approval_not_pending', 'This approval is no longer pending.', array( 'status' => 409, 'approval_status' => $record['status'] ) );
		}
		$record['status'] = 'approved';
		$record['approved_at'] = time();
		set_transient( self::key( $id ), $record, max( 1, (int) $record['expires_at'] - time() ) );
		return true;
	}

	public static function reject( $id, $user_id ) {
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		if ( (int) $user_id !== (int) $record['owner_user_id'] ) {
			return new WP_Error( 'approval_forbidden', 'Only the Direct MCP token owner can reject this operation.', array( 'status' => 403 ) );
		}
		$record['status'] = 'rejected';
		$record['rejected_at'] = time();
		set_transient( self::key( $id ), $record, max( 1, (int) $record['expires_at'] - time() ) );
		return true;
	}

	public static function consume( $id, $operation, $payload ) {
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		if ( 'approved' !== $record['status'] ) {
			return new WP_Error( 'approval_not_approved', 'This operation does not have an unused approval.', array( 'status' => 409, 'approval_status' => $record['status'] ) );
		}
		if ( sanitize_key( $operation ) !== $record['operation'] || ! hash_equals( $record['payload_hash'], self::payload_hash( $payload ) ) ) {
			return new WP_Error( 'approval_payload_mismatch', 'The approved operation does not match this request.', array( 'status' => 409 ) );
		}
		if ( (int) get_option( 'wpvdmcp_user_id', 0 ) !== (int) $record['owner_user_id'] ) {
			return new WP_Error( 'approval_owner_changed', 'The Direct MCP token owner changed after approval.', array( 'status' => 409 ) );
		}
		$record['status'] = 'consumed';
		$record['consumed_at'] = time();
		set_transient( self::key( $id ), $record, max( 1, (int) $record['expires_at'] - time() ) );
		return true;
	}

	public static function status( $id ) {
		$record = self::get_record( $id );
		return is_wp_error( $record ) ? $record : self::safe_record( $record );
	}

	public static function bypass_enabled() {
		if ( class_exists( 'WPVibe_Approval_Bypass' ) && method_exists( 'WPVibe_Approval_Bypass', 'is_on' ) ) {
			return (bool) WPVibe_Approval_Bypass::is_on();
		}
		$value = get_option( 'wpvibe_bypass_approvals', '' );
		return is_array( $value ) && ! empty( $value['enabled'] );
	}

	private static function get_record( $id ) {
		$id = (string) $id;
		if ( ! preg_match( '/^ap_[a-f0-9]{32}$/', $id ) ) {
			return new WP_Error( 'invalid_approval_id', 'Invalid approval ID.', array( 'status' => 400 ) );
		}
		$record = get_transient( self::key( $id ) );
		if ( ! is_array( $record ) ) {
			return new WP_Error( 'approval_expired', 'Approval was not found or has expired.', array( 'status' => 410 ) );
		}
		if ( empty( $record['expires_at'] ) || (int) $record['expires_at'] < time() ) {
			delete_transient( self::key( $id ) );
			return new WP_Error( 'approval_expired', 'Approval has expired.', array( 'status' => 410 ) );
		}
		return $record;
	}

	private static function safe_record( $record ) {
		return array(
			'approval_id' => $record['id'],
			'status' => $record['status'],
			'operation' => $record['operation'],
			'summary' => $record['summary'],
			'expires_at' => $record['expires_at'],
			'approval_url' => admin_url( 'admin.php?page=wpvibe-direct-mcp&approval=' . rawurlencode( $record['id'] ) ),
		);
	}

	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, 0, $length ) : substr( (string) $value, 0, $length );
	}

	private static function key( $id ) {
		return 'wpvdmcp_approval_' . $id;
	}

	private static function payload_hash( $payload ) {
		$payload = self::canonicalize( $payload );
		return hash( 'sha256', (string) wp_json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	private static function canonicalize( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value );
		}
		foreach ( $value as $key => $item ) {
			$value[ $key ] = self::canonicalize( $item );
		}
		return $value;
	}
}
