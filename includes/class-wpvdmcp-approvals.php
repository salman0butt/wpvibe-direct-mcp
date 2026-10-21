<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Browser-admin approval receipts for Direct MCP write/destructive operations. */
final class WPVDMCP_Approvals {
	const TTL = 1800;

	public static function request( $operation, $payload, $summary = '' ) {
		$owner = (int) get_option( 'wpvdmcp_user_id', 0 );
		$token_hash = get_option( 'wpvdmcp_token_hash', '' );
		if ( ! $owner || ! is_string( $token_hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $token_hash ) ) {
			return new WP_Error( 'approval_owner_missing', 'Direct MCP has no valid Administrator token owner and credential.', array( 'status' => 401 ) );
		}
		$id = 'ap_' . bin2hex( random_bytes( 16 ) );
		$record = array(
			'id' => $id,
			'owner_user_id' => $owner,
			'token_hash_fingerprint' => hash( 'sha256', $token_hash ),
			'operation' => sanitize_key( $operation ),
			'payload_hash' => self::payload_hash( $payload ),
			'display_payload' => self::display_payload( $payload ),
			'summary' => self::truncate( sanitize_text_field( $summary ), 500 ),
			'status' => 'pending',
			'created_at' => time(),
			'expires_at' => time() + self::TTL,
		);
		if ( ! set_transient( self::key( $id ), $record, self::TTL ) || $record !== get_transient( self::key( $id ) ) ) {
			return new WP_Error( 'approval_persist_failed', 'The approval request could not be durably persisted. No approval was created.', array( 'status' => 503 ) );
		}
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
		$claim = self::acquire_claim( $id );
		if ( is_wp_error( $claim ) ) {
			return $claim;
		}
		$release_claim = true;
		try {
			// Re-read under the shared claim so a concurrent approve/reject/consume
			// cannot make this decision from stale pending state.
			$record = self::get_record( $id );
			if ( is_wp_error( $record ) ) {
				return $record;
			}
			if ( $user_id !== (int) $record['owner_user_id'] || ! $user || ! user_can( $user, 'manage_options' ) ) {
				return new WP_Error( 'approval_forbidden', 'Only the Direct MCP token owner can approve this operation.', array( 'status' => 403 ) );
			}
			if ( 'pending' !== $record['status'] ) {
				return new WP_Error( 'approval_not_pending', 'This approval is no longer pending.', array( 'status' => 409, 'approval_status' => $record['status'] ) );
			}
			$record['status'] = 'approved';
			$record['approved_at'] = time();
			$stored = set_transient( self::key( $id ), $record, max( 1, (int) $record['expires_at'] - time() ) );
			if ( $stored ) {
				$persisted = get_transient( self::key( $id ) );
				if ( ! is_array( $persisted ) || $record !== $persisted ) {
					$stored = false;
					$release_claim = false; // Acknowledged but uncertain durable decision: block conflicting decisions.
				}
			}
			if ( ! $stored ) {
				return new WP_Error( 'approval_persist_failed', 'The approved state could not be verified as durable. Do not retry this receipt; an uncertain write remains locked until expiry.', array( 'status' => 503 ) );
			}
			return true;
		} finally {
			if ( $release_claim ) {
				delete_option( self::claim_key( $id ) );
			}
		}
	}

	public static function reject( $id, $user_id ) {
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$user_id = (int) $user_id;
		if ( $user_id !== (int) $record['owner_user_id'] ) {
			return new WP_Error( 'approval_forbidden', 'Only the Direct MCP token owner can reject this operation.', array( 'status' => 403 ) );
		}
		if ( 'pending' !== $record['status'] ) {
			return new WP_Error( 'approval_not_pending', 'This approval is no longer pending.', array( 'status' => 409, 'approval_status' => $record['status'] ) );
		}
		$claim = self::acquire_claim( $id );
		if ( is_wp_error( $claim ) ) {
			return $claim;
		}
		$release_claim = true;
		try {
			// Re-read under the shared claim so a concurrent approve/reject/consume
			// cannot make this decision from stale pending state.
			$record = self::get_record( $id );
			if ( is_wp_error( $record ) ) {
				return $record;
			}
			if ( $user_id !== (int) $record['owner_user_id'] ) {
				return new WP_Error( 'approval_forbidden', 'Only the Direct MCP token owner can reject this operation.', array( 'status' => 403 ) );
			}
			if ( 'pending' !== $record['status'] ) {
				return new WP_Error( 'approval_not_pending', 'This approval is no longer pending.', array( 'status' => 409, 'approval_status' => $record['status'] ) );
			}
			$record['status'] = 'rejected';
			$record['rejected_at'] = time();
			$stored = set_transient( self::key( $id ), $record, max( 1, (int) $record['expires_at'] - time() ) );
			if ( $stored ) {
				$persisted = get_transient( self::key( $id ) );
				if ( ! is_array( $persisted ) || $record !== $persisted ) {
					$stored = false;
					$release_claim = false; // Do not let another decision race an uncertain persisted state.
				}
			}
			if ( ! $stored ) {
				return new WP_Error( 'approval_persist_failed', 'The rejected state could not be verified as durable. Do not retry this receipt; an uncertain write remains locked until expiry.', array( 'status' => 503 ) );
			}
			return true;
		} finally {
			if ( $release_claim ) {
				delete_option( self::claim_key( $id ) );
			}
		}
	}

	public static function consume( $id, $operation, $payload ) {
		$record = self::get_record( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$claim = self::acquire_claim( $id );
		if ( is_wp_error( $claim ) ) {
			return $claim;
		}
		$release_claim = true;
		try {
			// Re-read only after the atomic claim. Another request may have consumed
			// the approval between the optimistic read above and acquiring this lock.
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
				$stored = set_transient( self::key( $id ), $record, max( 1, (int) $record['expires_at'] - time() ) );
				if ( $stored ) {
					$persisted = get_transient( self::key( $id ) );
					$stored = is_array( $persisted ) && $persisted === $record;
				}
				if ( ! $stored ) {
					// The destructive caller must not run when we cannot prove the receipt
					// was durably consumed. Keep the claim until the receipt expires so a
					// later request cannot replay an approval whose state is uncertain.
					$release_claim = false;
					return new WP_Error( 'approval_persist_failed', 'The approval could not be durably marked as consumed. The operation was not executed and this approval is locked until it expires.', array( 'status' => 503 ) );
				}
				return true;
			} finally {
				if ( $release_claim ) {
					delete_option( self::claim_key( $id ) );
				}
			}
	}

	public static function status( $id ) {
		$record = self::get_record( $id );
		return is_wp_error( $record ) ? $record : self::safe_record( $record );
	}

	public static function bypass_enabled() {
		$value = get_option( 'wpvibe_bypass_approvals', '' );
		if ( ! is_array( $value ) || ! array_key_exists( 'enabled', $value ) || true !== $value['enabled'] ) {
			return false;
		}
		if ( class_exists( 'WPVibe_Approval_Bypass' ) && method_exists( 'WPVibe_Approval_Bypass', 'is_on' ) ) {
			return (bool) WPVibe_Approval_Bypass::is_on();
		}
		return true;
	}

	private static function get_record( $id ) {
		$id = (string) $id;
		if ( ! preg_match( '/^ap_[a-f0-9]{32}$/', $id ) ) {
			return new WP_Error( 'invalid_approval_id', 'Invalid approval ID.', array( 'status' => 400 ) );
		}
		$record = get_transient( self::key( $id ) );
		if ( ! is_array( $record ) ) {
			delete_option( self::claim_key( $id ) );
			return new WP_Error( 'approval_expired', 'Approval was not found or has expired.', array( 'status' => 410 ) );
		}
		if ( ! self::is_valid_record( $record, $id ) ) {
			delete_transient( self::key( $id ) );
			delete_option( self::claim_key( $id ) );
			return new WP_Error( 'approval_corrupt', 'Approval state is invalid and cannot be used.', array( 'status' => 410 ) );
		}
		if ( (int) $record['expires_at'] < time() ) {
			delete_transient( self::key( $id ) );
			delete_option( self::claim_key( $id ) );
			return new WP_Error( 'approval_expired', 'Approval has expired.', array( 'status' => 410 ) );
		}
		$live_hash = get_option( 'wpvdmcp_token_hash', '' );
		if ( (int) get_option( 'wpvdmcp_user_id', 0 ) !== (int) $record['owner_user_id'] || ! is_string( $live_hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $live_hash ) || ! hash_equals( $record['token_hash_fingerprint'], hash( 'sha256', $live_hash ) ) ) {
			return new WP_Error( 'approval_credential_changed', 'The token owner or credential changed. Request a new approval with the current Direct MCP token.', array( 'status' => 409 ) );
		}
		return $record;
	}

	private static function is_valid_record( $record, $id ) {
		return isset( $record['id'], $record['owner_user_id'], $record['operation'], $record['payload_hash'], $record['summary'], $record['status'], $record['expires_at'] )
			&& $record['id'] === $id
			&& is_int( $record['owner_user_id'] ) && $record['owner_user_id'] > 0
			&& isset( $record['token_hash_fingerprint'] ) && is_string( $record['token_hash_fingerprint'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/D', $record['token_hash_fingerprint'] )
			&& is_string( $record['operation'] )
			&& is_string( $record['payload_hash'] ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $record['payload_hash'] )
			&& is_string( $record['summary'] )
			&& in_array( $record['status'], array( 'pending', 'approved', 'rejected', 'consumed' ), true )
			&& is_numeric( $record['expires_at'] );
	}

	private static function safe_record( $record ) {
		return array(
			'approval_id' => $record['id'],
			'status' => $record['status'],
			'operation' => $record['operation'],
			'summary' => $record['summary'],
			'display_payload' => isset( $record['display_payload'] ) ? $record['display_payload'] : array(),
			'expires_at' => $record['expires_at'],
			'approval_url' => admin_url( 'admin.php?page=wpvibe-direct-mcp&approval=' . rawurlencode( $record['id'] ) ),
		);
	}

	private static function truncate( $value, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $value, 0, $length ) : substr( (string) $value, 0, $length );
	}

	private static function display_payload( $value, $depth = 0 ) {
		if ( $depth > 5 ) { return '[TRUNCATED]'; }
		if ( is_array( $value ) ) {
			$out = array(); $count = 0;
			foreach ( $value as $key => $item ) {
				if ( $count++ >= 50 ) { $out['__truncated__'] = true; break; }
				$key_text = is_string( $key ) ? $key : (string) $key;
				if ( preg_match( '/pass(word)?|secret|token|cookie|authorization|api[_-]?key|credential/i', $key_text ) ) {
					$out[ $key ] = '[REDACTED]';
				} else {
					$out[ $key ] = self::display_payload( $item, $depth + 1 );
				}
			}
			return $out;
		}
		if ( is_object( $value ) ) { return self::display_payload( (array) $value, $depth + 1 ); }
		if ( is_string( $value ) ) { return self::truncate( $value, 2000 ); }
		return is_scalar( $value ) || null === $value ? $value : '[UNSUPPORTED]';
	}

	private static function key( $id ) {
		return 'wpvdmcp_approval_' . $id;
	}

	private static function claim_key( $id ) {
		return 'wpvdmcp_approval_claim_' . hash( 'sha256', (string) $id );
	}

	private static function acquire_claim( $id ) {
		$key = self::claim_key( $id );
		$claim = array( 'claimed_at' => time() );
		if ( add_option( $key, $claim, '', false ) ) {
			return true;
		}
		return new WP_Error( 'approval_in_progress', 'This one-time approval is already being changed or consumed by another request.', array( 'status' => 409 ) );
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
